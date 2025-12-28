<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
// use Google\Client; // <- Удалено
// use Google\Service\AndroidPublisher; // <- Удалено
use App\Models\User;
use Carbon\Carbon;
use App\Services\Subscriptions\GooglePlayVerifier; // <- Добавлено

class SubscriptionsController extends Controller
{
    /**
     * Верификация покупки Google Play (через GooglePlayVerifier).
     *
     * Роут:
     * Route::post('/subscriptions/play/verify', [SubscriptionsController::class, 'verifyGooglePlay']);
     *
     * Поддерживает оба варианта ключей из клиента:
     * - camelCase: purchaseToken, productId, packageName (или без него)
     * - snake_case: purchase_token, product_id, package_name (или без него)
     */
    public function verifyGooglePlay(Request $request, GooglePlayVerifier $verifier) // <-- Внедряем сервис
    {
        // 1. Валидация
        $purchaseToken = $request->input('purchaseToken', $request->input('purchase_token'));
        $productId     = $request->input('productId',     $request->input('product_id'));
        $packageName   = $request->input('packageName',   $request->input('package_name')); // Сервис сам подставит default, если null

        if (empty($purchaseToken) || empty($productId)) {
            return response()->json([
                'error'   => 'Validation failed',
                'details' => 'purchaseToken/productId is required'
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        // 2. Вызов сервиса (Механизм 2)
        try {
            // verifyAndUpsert сам вызовет Google API, создаст/обновит Subscription
            // и обновит $user->is_paid / $user->paid_until
            $subscription = $verifier->verifyAndUpsert($user, [
                'purchaseToken' => $purchaseToken,
                'productId'     => $productId,
                'packageName'   => $packageName,
            ]);

            Log::info('Google verify (V2 via Service) successful', [
                'user_id' => $user->id,
                'sub_id'  => $subscription->id,
                'status'  => $subscription->status,
            ]);

            // 3. Проверка статуса (сервис уже обновил $user->is_paid)
            // Мы перезагружаем пользователя, чтобы получить свежие данные is_paid
            $user->refresh();

            if ($user->is_paid) {
                // Возвращаем как в /auth/me (для обновления клиента)
                $user->load('favorites', 'listens.book', 'listens.chapter');
                $user->append('credits');

                return response()->json([
                    'success' => true,
                    'state'   => $subscription->status, // 'active', 'grace', 'on_hold' и т.д.
                    'product' => $subscription->product_id,
                    'expiry'  => $subscription->expires_at?->toIso8601String(),
                    'user'    => $user,
                ]);
            }

            // Не активна (например, 'expired' или 'canceled')
            return response()->json([
                'success' => false,
                'state'   => $subscription->status,
                'message' => 'Subscription not active',
            ], 400);

        } catch (\Google\Service\Exception $e) {
            // Ошибка со стороны Google API
            Log::error('Google API error during verification (V2 via Service)', [
                'user_id' => $user?->id,
                'code'    => $e->getCode(),
                'msg'     => $e->getMessage(),
            ]);
            return response()->json([
                'error'   => 'Google API validation failed',
                'details' => $e->getMessage(),
            ], 400);

        } catch (\Throwable $e) {
            // Общая ошибка сервера
            Log::error('General error during verification (V2 via Service)', [
                'user_id' => $user?->id,
                'msg'     => $e->getMessage(),
            ]);
            return response()->json([
                'error'   => 'Server error during verification',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Текущий статус подписки пользователя (как и прежде).
     */
    public function status(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('favorites', 'listens.book', 'listens.chapter');
        $user->append('credits');

        return response()->json($user);
    }
}