<?php
// app/Http/Controllers/Api/DeviceTokenController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    /** Регистрация/обновление токена устройства (работает для гостей и залогиненных). */
    public function store(Request $request)
    {
        // Поддерживаем и JSON, и form-urlencoded
        $payload = $request->json()->all();
        if (empty($payload)) {
            $payload = $request->all();
        }

        // Валидация
        $v = Validator::make($payload, [
            'token'       => ['required', 'string', 'max:512'],
            'platform'    => ['nullable', 'string', Rule::in(['android', 'ios', 'other'])],
            'app_version' => ['nullable', 'string', 'max:50'],
            'meta'        => ['nullable', 'array'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $token = $data['token'];

        // 1. Визначаємо user_id (автоматично або вручну через Sanctum)
        $userId = optional($request->user())->id;

        // РУЧНАЯ ПРОВЕРКА (исправление "гонки" при логине)
        if (!$userId) {
            try {
                $user = auth('sanctum')->user();
                if ($user) {
                    $userId = $user->id;
                    Log::info('[Push Register] Користувача знайдено вручну через sanctum guard', ['user_id' => $userId]);
                }
            } catch (\Throwable $e) {}
        }

        // 2. ЕДИНЫЙ ПОТОК ОБНОВЛЕНИЯ
        // Мы всегда ищем по токену. Это предотвращает дубликаты и ошибки SQL.
        
        $tokenRecord = DeviceToken::updateOrCreate(
            ['token' => $token], // 👈 Главный ключ поиска
            [
                'user_id'      => $userId, // Привязываем к юзеру (или null)
                'platform'     => $data['platform'] ?? null,
                'app_version'  => $data['app_version'] ?? null,
                'meta'         => $data['meta'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        Log::info('[Push Register] Токен оброблено успішно', [
            'token_id' => $tokenRecord->id,
            'user_id'  => $userId,
            'is_new'   => $tokenRecord->wasRecentlyCreated
        ]);

        // 3. Опціонально: Очистка старых "хвостов"
        // Если у юзера есть ДРУГОЙ токен на ЭТОЙ же платформе — удаляем его.
        // Это гарантирует, что у одного юзера на Android всегда только 1 активный токен.
        if ($userId && !empty($data['platform'])) {
            DeviceToken::where('user_id', $userId)
                ->where('platform', $data['platform'])
                ->where('token', '!=', $token) // Не видаляємо поточний
                ->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Удаление токена (опц.). Для авторизованного — ограничиваемся его токенами. */
    public function destroy(Request $request)
    {
        $payload = $request->json()->all();
        if (empty($payload)) {
            $payload = $request->all();
        }

        $v = Validator::make($payload, [
            'token' => ['required', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $token = $v->validated()['token'];

        $q = DeviceToken::where('token', $token);
        if ($request->user()) {
            $uid = $request->user()->id;
            $q->where(function ($w) use ($uid) {
                $w->whereNull('user_id')->orWhere('user_id', $uid);
            });
        }

        $deleted = $q->delete();

        return response()->json(['ok' => $deleted > 0]);
    }

    /** Быстрый тест: отправка пуша на все устройства текущего пользователя. */
    public function test(Request $request, FcmService $fcm)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'Auth required'], 401);
        }

        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
        if (empty($tokens)) {
            return response()->json(['ok' => false, 'error' => 'No tokens for user'], 404);
        }

        $sent = 0;
        foreach ($tokens as $t) {
            $ok = $fcm->sendToToken(
                token: $t,
                title: 'Booka: тестовое уведомление',
                body:  'Это push от вашего сервера. Всё работает!',
                data:  ['route' => '/profile']
            );
            if ($ok) $sent++;
        }

        return response()->json(['ok' => true, 'sent' => $sent, 'total' => count($tokens)]);
    }
}