<?php

namespace App\Services\Subscriptions;

use App\Integrations\GooglePlayClient;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GooglePlayVerifier
{
    public function __construct(
        protected GooglePlayClient $play
    ) {}

    /**
     * Верифицирует покупку, денормализует статусы и обновляет users.is_paid / paid_until
     */
    public function verifyAndUpsert(User $user, array $payload): Subscription
    {
        $purchaseToken = $payload['purchaseToken'];
        $productId     = $payload['productId'];
        
        // 🚨 DEBUG: Начало верификации
        Log::info('GooglePlayVerifier: Starting verification.', [
            'user_id' => $user->id,
            'product_id' => $productId,
            'token_suffix' => substr($purchaseToken, -10),
        ]);

        // Используем config, но с фоллбэком, если конфиг не настроен
        $packageName = $payload['packageName'] 
            ?? config('services.google_play.package_name') 
            ?? 'com.booka_app'; // Хардкод для надежности, если конфиг пуст

        // Получаем данные от Google API
        // 🚨 DEBUG: Логируем обращение к Google API
        Log::debug('GooglePlayVerifier: Calling Google API...');
        $raw = $this->play->getSubscriptionV2($purchaseToken, $packageName);
        
        // 🚨 DEBUG: Логируем сырой ответ от Google API (только статус и важные поля)
        Log::info('GooglePlayVerifier: Raw response received.', [
            'token_suffix' => substr($purchaseToken, -10), 
            'subscriptionState' => $raw['subscriptionState'] ?? 'N/A',
            'expiryTime' => $raw['expiryTime'] ?? 'N/A',
            'raw_acknowledgementState' => $raw['acknowledgementState'] ?? 'N/A',
        ]);

        // Если покупка еще не подтверждена, подтверждаем ее на стороне Google
        $this->acknowledgeIfNeeded($raw, $productId, $purchaseToken, $packageName);
        
        $norm = $this->normalizeV2($raw);

        // 🚨 DEBUG: Начало транзакции в БД
        Log::debug('GooglePlayVerifier: Starting DB transaction...');
        
        return DB::transaction(function () use ($user, $purchaseToken, $productId, $packageName, $norm, $raw) {
            $sub = Subscription::query()->where('purchase_token', $purchaseToken)->first();
            
            if (!$sub) {
                $sub = new Subscription();
                $sub->user_id      = $user->id;
                $sub->platform     = 'google';
                $sub->package_name = $packageName;
                $sub->product_id   = $productId;
                $sub->purchase_token = $purchaseToken;
                Log::debug('GooglePlayVerifier: Creating new subscription record.');
            }

            $sub->order_id        = $norm['order_id'] ?? null;
            $sub->status          = $norm['status'];
            $sub->started_at      = $norm['started_at'];
            $sub->renewed_at      = $norm['renewed_at']; 
            $sub->expires_at      = $norm['expires_at'];
            $sub->acknowledged_at = $norm['acknowledged_at'];
            $sub->canceled_at     = $norm['canceled_at'];
            $sub->raw_payload     = $raw;
            $sub->latest_rtdn_at  = now();
            $sub->save();

            // Обновляем пользователя (денормализация)
            $paidUntil = null;

            if (!empty($sub->expires_at)) {
                $paidUntil = Carbon::parse($sub->expires_at);
            }

            $isPaid = $this->determineIsPaid($sub->status, $paidUntil);

            $user->is_paid = $isPaid ? 1 : 0;
            $user->paid_until = $paidUntil;
            $user->save();
            
            Log::info('GooglePlayVerifier: User and Subscription updated.', [
                'user_id' => $user->id, 
                'sub_status' => $sub->status,
                'is_paid' => $user->is_paid, 
                'expires_at' => $paidUntil ? $paidUntil->toDateTimeString() : 'null'
            ]);

            return $sub;
        });
        
        // 🚨 DEBUG: Конец транзакции в БД
        Log::debug('GooglePlayVerifier: DB transaction finished.');
    }

    /** Нормализация ответа Subscriptions V2 */
    private function normalizeV2(array $g): array
    {
        $orderId  = $g['latestOrderId'] ?? ($g['lineItems'][0]['offerDetails']['basePlanId'] ?? null);

        // Статус
        $state = $g['subscriptionState'] ?? null;
        $map = [
            'SUBSCRIPTION_STATE_UNSPECIFIED'     => 'pending', // google пока не определил (обычно сразу после покупки)
            'SUBSCRIPTION_STATE_ACTIVE'          => 'active',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace',
            'SUBSCRIPTION_STATE_ON_HOLD'         => 'on_hold',
            'SUBSCRIPTION_STATE_PAUSED'          => 'paused',
            'SUBSCRIPTION_STATE_CANCELED'        => 'canceled', // Отменена юзером, но срок может еще не выйти
            'SUBSCRIPTION_STATE_EXPIRED'         => 'expired',  // Срок вышел окончательно
            'SUBSCRIPTION_STATE_PENDING'         => 'pending',  // Покупка инициирована, но не завершена
        ];
        $status = $map[$state] ?? 'unknown';

        if ($status === 'unknown') {
            Log::warning('GooglePlayVerifier: Unknown subscriptionState', [
                'subscriptionState' => $state,
                'payload_sample' => substr(json_encode($g), 0, 500),
            ]);
        }

        // Парсинг даты с учетом возможного формата (строка ISO 8601)
        // Google V2 обычно возвращает ISO строки: "2023-01-01T12:00:00.123Z"
        $parseDate = function($val) {
            if (!$val) return null;
            try {
                return Carbon::parse($val);
            } catch (\Exception $e) {
                Log::warning('GooglePlayVerifier: Date parse error', ['val' => $val, 'error' => $e->getMessage()]);
                return null;
            }
        };

        $start  = $g['startTime']  ?? null;
        // В V2 нет явного renewed_at в корне, можно пробовать брать из lineItems, но это ненадежно. Оставим null.
        $renew  = null; 

        // expiryTime — самое важное поле.
        // В структуре V2 оно часто лежит внутри lineItems, если это мульти-подписка, 
        // но Google часто дублирует его или возвращает в корне для простых подписок.
        // Проверяем все места.
        $expire = $g['expiryTime'] ?? null;
        if (!$expire && !empty($g['lineItems'])) {
            // Берем у первого элемента (обычно он один)
            $expire = $g['lineItems'][0]['expiryTime'] ?? null;
        }

        $ack = ($g['acknowledgementState'] ?? null) === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';

        // cancelTime
        $cancel = $g['canceledStateContext']['userInitiatedCancellation']['cancelTime'] ?? null;
        // Бывает еще systemInitiatedCancellation
        if (!$cancel) {
             $cancel = $g['canceledStateContext']['systemInitiatedCancellation']['cancelTime'] ?? null;
        }

        return [
            'order_id'        => $orderId,
            'status'          => $status,
            'started_at'      => $parseDate($start),
            'renewed_at'      => $parseDate($renew),
            'expires_at'      => $parseDate($expire),
            'acknowledged_at' => $ack ? now() : null,
            'canceled_at'     => $parseDate($cancel),
        ];
    }

    /** Подтверждает покупку в Google Play, если она еще не ACK'd */
    private function acknowledgeIfNeeded(array &$raw, string $productId, string $purchaseToken, ?string $packageName): void
    {
        // 🚨 DEBUG: Проверка необходимости acknowledge
        Log::debug('GooglePlayVerifier: Checking acknowledge state.', ['current_state' => $raw['acknowledgementState'] ?? 'N/A']);
        
        $isAcknowledged = ($raw['acknowledgementState'] ?? null) === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';

        if ($isAcknowledged) {
            Log::debug('GooglePlayVerifier: Purchase already acknowledged. Skipping.');
            return;
        }

        try {
            Log::info('GooglePlayVerifier: Attempting to acknowledge purchase...');
            $this->play->acknowledgeSubscription($productId, $purchaseToken, $packageName);
            $raw['acknowledgementState'] = 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';

            Log::info('GooglePlayVerifier: Purchase acknowledged successfully.', [
                'token_suffix' => substr($purchaseToken, -10),
                'product_id' => $productId,
            ]);
        } catch (\Throwable $e) {
            Log::error('GooglePlayVerifier: Failed to acknowledge purchase (CRITICAL).', [
                'token_suffix' => substr($purchaseToken, -10),
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            // Не бросаем ошибку, так как это не должно блокировать остальную обработку, 
            // но логируем как CRITICAL, так как Google будет спамить RTDN, пока не получит ACK.
        }
    }

    /**
     * Определяет, должен ли пользователь получить доступ на основе статуса и даты окончания подписки.
     */
    private function determineIsPaid(string $status, ?Carbon $paidUntil): bool
    {
        if (!$paidUntil) {
            return false;
        }

        // 5-минутный буфер, чтобы учесть задержки получения RTDN или округления времени
        $isTimeValid = $paidUntil->isAfter(now()->subMinutes(5));

        // 🚨 DEBUG: Логика определения платности
        Log::debug('GooglePlayVerifier: Determine paid status.', [
            'sub_status' => $status,
            'paid_until' => $paidUntil->toDateTimeString(),
            'is_time_valid' => $isTimeValid
        ]);

        return match ($status) {
            'active', 'grace' => $isTimeValid,
            'canceled' => $isTimeValid,
            'on_hold', 'paused', 'pending', 'expired' => false,
            default => false,
        };
    }
}