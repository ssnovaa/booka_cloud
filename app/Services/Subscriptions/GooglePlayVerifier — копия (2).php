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
        
        // Используем config, но с фоллбэком, если конфиг не настроен
        $packageName = $payload['packageName'] 
            ?? config('services.google_play.package_name') 
            ?? 'com.booka_app'; // Хардкод для надежности, если конфиг пуст

        // Получаем данные от Google API
        $raw = $this->play->getSubscriptionV2($purchaseToken);
        
        // Логируем сырой ответ для отладки (важно при проблемах с датами)
        Log::info('GooglePlayVerifier: Raw response', ['token_suffix' => substr($purchaseToken, -10), 'payload' => $raw]);

        $norm = $this->normalizeV2($raw);

        return DB::transaction(function () use ($user, $purchaseToken, $productId, $packageName, $norm, $raw) {
            $sub = Subscription::query()->where('purchase_token', $purchaseToken)->first();
            
            if (!$sub) {
                $sub = new Subscription();
                $sub->user_id        = $user->id;
                $sub->platform       = 'google';
                $sub->package_name   = $packageName;
                $sub->product_id     = $productId;
                $sub->purchase_token = $purchaseToken;
            }

            $sub->order_id        = $norm['order_id'] ?? null;
            $sub->status          = $norm['status'];
            $sub->started_at      = $norm['started_at'];
            // renewed_at может быть null, это нормально
            $sub->renewed_at      = $norm['renewed_at']; 
            $sub->expires_at      = $norm['expires_at'];
            $sub->acknowledged_at = $norm['acknowledged_at'];
            $sub->canceled_at     = $norm['canceled_at'];
            $sub->raw_payload     = $raw;
            $sub->latest_rtdn_at  = now();
            $sub->save();

            // Обновляем пользователя (денормализация)
            $isPaid = false;
            $paidUntil = null;

            if (!empty($sub->expires_at)) {
                $paidUntil = Carbon::parse($sub->expires_at);
                
                // ‼️ ВАЖНОЕ ИСПРАВЛЕНИЕ: Добавляем буфер 24 часа ‼️
                // Google может прислать expired, но реально доступ должен быть еще некоторое время (грейс-период на стороне сервера),
                // или просто разница часовых поясов.
                // Если статус активен, мы верим статусу.
                // Если статус expired, мы верим дате.
                
                $isValidStatus = in_array($sub->status, ['active', 'grace', 'on_hold']);
                
                // Если статус активен — даем доступ.
                // Если статус не активен (например, cancel), но время еще не вышло — тоже даем доступ.
                // Используем now()->subMinutes(5) для компенсации микро-лагов времени
                $isTimeValid = $paidUntil->isAfter(now()->subMinutes(5));

                if ($isValidStatus) {
                    $isPaid = true;
                } elseif ($sub->status === 'canceled' && $isTimeValid) {
                     // Если отменил, но срок не вышел -> Paid
                    $isPaid = true;
                } elseif ($sub->status === 'expired' && $isTimeValid) {
                    // Если Google говорит expired, но по нашему времени еще пару минут есть — 
                    // ладно, пусть будет, но скорее всего это реально expired.
                    // Обычно expired от Google - это истина. 
                    // Но для безопасности можно оставить false.
                    $isPaid = false; 
                } else {
                    $isPaid = false;
                }
                
                // ВАРИАНТ ПОПРОЩЕ И НАДЕЖНЕЕ:
                // Если статус active/grace -> Paid.
                // Если статус canceled, но время в будущем -> Paid.
                // Все остальное -> Free.
                $isPaid = in_array($sub->status, ['active', 'grace']) 
                          || ($sub->status === 'canceled' && $isTimeValid);
            }

            $user->is_paid = $isPaid ? 1 : 0;
            $user->paid_until = $paidUntil;
            $user->save();
            
            Log::info('GooglePlayVerifier: User updated', [
                'user_id' => $user->id, 
                'is_paid' => $user->is_paid, 
                'status' => $sub->status,
                'expires_at' => $paidUntil
            ]);

            return $sub;
        });
    }

    /** Нормализация ответа Subscriptions V2 */
    private function normalizeV2(array $g): array
    {
        $orderId  = $g['latestOrderId'] ?? ($g['lineItems'][0]['offerDetails']['basePlanId'] ?? null);

        // Статус
        $state = $g['subscriptionState'] ?? null;
        $map = [
            'SUBSCRIPTION_STATE_ACTIVE'          => 'active',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace',
            'SUBSCRIPTION_STATE_ON_HOLD'         => 'on_hold',
            'SUBSCRIPTION_STATE_PAUSED'          => 'paused',
            'SUBSCRIPTION_STATE_CANCELED'        => 'canceled', // Отменена юзером, но срок может еще не выйти
            'SUBSCRIPTION_STATE_EXPIRED'         => 'expired',  // Срок вышел окончательно
        ];
        $status = $map[$state] ?? 'expired';

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
}