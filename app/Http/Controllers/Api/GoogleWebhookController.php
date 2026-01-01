<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\DeviceToken;
use App\Services\Subscriptions\GooglePlayVerifier;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleWebhookController extends Controller
{
    /**
     * Обробка сповіщень RTDN від Google Pub/Sub.
     *
     * Маршрут налаштований в routes/api.php:
     * Route::post('/google/rtdn', [GoogleWebhookController::class, 'handleRtdn']);
     */
    public function handleRtdn(Request $request, GooglePlayVerifier $verifier, FcmService $fcmService)
    {
        // 1. Декодуємо повідомлення від Google (Pub/Sub).
        $raw = $request->input('message.data');
        $payload = $raw ? json_decode(base64_decode($raw), true) : null;

        if (!$payload) {
            Log::warning('RTDN: Недійсний Pub/Sub payload', [
                'body' => $request->all(),
            ]);

            return response()->json(['status' => 'bad_request'], 400);
        }

        // 2. Витягуємо subscriptionNotification або testNotification
        $notification = $payload['subscriptionNotification']
            ?? $payload['testNotification']
            ?? null;

        $purchaseToken = $notification['purchaseToken'] ?? null;
        $productId     = $notification['subscriptionId'] ?? null;
        $notificationType = $notification['notificationType'] ?? 'N/A';

        if (!$purchaseToken) {
            Log::info('RTDN: Отримано сповіщення без purchaseToken (можливо, тестове)', [
                'payload' => $payload,
            ]);

            return response()->json(['status' => 'ok_no_token'], 200);
        }

        Log::info('RTDN: Отримано сповіщення', [
            'token' => $purchaseToken,
            'type'  => $notificationType,
        ]);

        // 3. Шукаємо підписку по purchase_token
        /** @var Subscription|null $subscription */
        $subscription = Subscription::where('purchase_token', $purchaseToken)->first();

        if (!$subscription || !$subscription->user) {
            Log::error('RTDN: Отримано токен, якого немає в нашій БД', [
                'token' => $purchaseToken,
            ]);

            // Важливо: завжди відповідаємо 200, щоб Google не спамив повторно
            return response()->json(['status' => 'subscription_not_found'], 200);
        }

$user    = $subscription->user;
// Використовуємо getRawOriginal, щоб отримати статус безпосередньо з БД, 
// ігноруючи автоматичну перевірку дати paid_until в моделі User
$wasPaid = (bool) $user->getRawOriginal('is_paid');

        // 4. Оновлюємо підписку через існуючий сервіс-верифікатор
        try {
            $verifier->verifyAndUpsert($user, [
                'purchaseToken' => $purchaseToken,
                'productId'     => $productId ?: $subscription->product_id,
                'packageName'   => $subscription->package_name,
            ]);

            $subscription->refresh();
            $user->refresh(); // отримуємо оновлений is_paid / paid_until

            Log::info('RTDN: Підписку успішно оновлено', [
                'user_id'    => $user->id,
                'token'      => $purchaseToken,
                'new_status' => $subscription->status,
            ]);

            // 5. 🟢 ЯВНЕ ПОВІДОМЛЕННЯ: Якщо статус змінився з платного на безкоштовний
            if ($wasPaid && !$user->is_paid) {
                Log::info(
                    'RTDN: Статус змінився (був платний -> став безкоштовний). Надсилаємо VISIBLE push.',
                    ['user_id' => $user->id]
                );
                
                // 🟢 Український текст для push-сповіщення
                $title = 'Термін Вашої підписки сплинув';
                $body = 'Дякуємо, що були з нами! Заходьте та оновіть підписку, щоб продовжити слухати без обмежень.';

                $tokens = DeviceToken::where('user_id', $user->id)
                    ->pluck('token')
                    ->all();

                if (!empty($tokens)) {
                    Log::info(
                        'RTDN: Знайдено ' . count($tokens) . ' токен(ів) для user_id=' . $user->id . '. Відправляємо...'
                    );

                    foreach ($tokens as $token) {
                        // А) Відправляємо VISIBLE push (для користувача)
                        $fcmService->sendToToken(
                            $token,
                            $title,
                            $body,
                            [
                                'type'   => 'subscription_update',
                                'status' => $user->is_paid ? 'paid' : 'free',
                            ]
                        );

                        // Б) 🔴 ДОДАНО: Тихий пуш (Data Message) для технічної зупинки плеєра
                        // Title та Body = null, щоб Android не перехоплював це в шторку, а віддав додатку
                        $fcmService->sendToToken(
                            $token,
                            null, 
                            null, 
                            [
                                'action' => 'force_stop_player', // Ключ, який ми будемо ловити у Flutter
                                'reason' => 'subscription_expired'
                            ]
                        );
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('RTDN: Помилка під час оновлення підписки', [
                'user_id' => $user->id,
                'msg'     => $e->getMessage(),
            ]);

            // І тут також відповідаємо 200, щоб Google не перезапускав нотифікацію
            return response()->json(['status' => 'server_error'], 200);
        }

        // 6. OK для Google
        return response()->json(['status' => 'ok'], 200);
    }
}