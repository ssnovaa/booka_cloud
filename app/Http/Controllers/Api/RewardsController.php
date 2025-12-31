<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\CreditsService; // Подключаем сервис для работы с балансом

class RewardsController extends Controller
{
    /**
     * POST /api/rewards/prepare
     * Создаём pending-событие ТОЛЬКО для авторизованного юзера.
     * Возвращаем одноразовый nonce, привязанный к user_id.
     */
    public function prepare(Request $r)
    {
        $user = $r->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $nonce = (string) Str::uuid();

        DB::table('ad_reward_events')->insert([
            'user_id'    => $user->id,
            'nonce'      => $nonce,
            'status'     => 'pending',
            'source'     => 'admob',
            'ip'         => $r->ip(),
            'ua'         => substr((string) $r->userAgent(), 0, 512),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'nonce'          => $nonce,
            'user_id'        => $user->id,
            'reward_minutes' => 15,
        ]);
    }

    /**
     * GET /api/rewards/status?nonce=...
     * Статус по nonce — только для авторизованных. Ничего не начисляет.
     */
    public function status(Request $r)
    {
        $user = $r->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $nonce = (string) $r->query('nonce', '');
        if ($nonce === '') {
            return response()->json(['status' => 'unknown'], 400);
        }

        $row = DB::table('ad_reward_events')->where('nonce', $nonce)->first();
        if (!$row || (int) $row->user_id !== (int) $user->id) {
            return response()->json(['status' => 'unknown'], 404);
        }

        return response()->json(['status' => $row->status ?? 'unknown'], 200);
    }

    /**
     * GET|POST /api/admob/ssv
     * Публичный SSV-коллбек от AdMob.
     */
    public function admobSsv(Request $r)
    {
        // 🔥 ЛОГИРОВАНИЕ: Записываем всё, что прислал Google, чтобы увидеть это в дебаг-роуте
        Log::info('AdMob SSV Request Incoming:', [
            'url' => $r->fullUrl(),
            'method' => $r->method(),
            'all_data' => $r->all()
        ]);

        // Читаем параметры
        $userId        = (int) $r->input('user_id', 0);
        $adUnitId      = (string) $r->input('ad_unit_id', '');
        $rewardAmount  = (int) $r->input('reward_amount', 0);
        
        $customRaw = $r->input('custom_data', '');
        $custom    = [];

        // Улучшенный парсинг custom_data
        if (is_array($customRaw)) {
            $custom = $customRaw;
        } elseif (is_string($customRaw) && $customRaw !== '') {
            $decoded = $customRaw;
            if (str_starts_with($decoded, '%7B') || str_contains($decoded, '%22')) {
                $decoded = urldecode($decoded);
            }
            try {
                $custom = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                Log::error('AdMob SSV: Failed to decode custom_data', ['raw' => $customRaw]);
            }
        }

        $nonce = $custom['nonce'] ?? null;
        
        // Если userId не пришел в основном запросе, пробуем взять его из custom_data
        if ($userId <= 0 && isset($custom['user_id'])) {
            $userId = (int) $custom['user_id'];
        }

        // Подставляем дефолт, если reward_amount пуст
        $minutesToAdd = $rewardAmount > 0 ? $rewardAmount : 15;

        // Валидация
        if ($userId < 1 || !$nonce) {
            Log::warning('admobSsv: invalid payload (missing user_id or nonce)', [
                'extracted_user_id' => $userId,
                'extracted_nonce' => $nonce,
                'full_input' => $r->all()
            ]);
            return response('ok', 200);
        }

        return DB::transaction(function () use ($userId, $adUnitId, $minutesToAdd, $nonce, $r) {
            // Ищем событие
            $event = DB::table('ad_reward_events')
                ->where('nonce', $nonce)
                ->lockForUpdate()
                ->first();

            // Если уже обработано — просто выходим
            if ($event && $event->status === 'granted') {
                return response('ok', 200);
            }

            // ✅ НАЧИСЛЯЕМ МИНУТЫ ЧЕРЕЗ СЕРВИС
            try {
                app(CreditsService::class)->addMinutes($userId, $minutesToAdd);
                Log::info("AdMob SSV: Successfully added $minutesToAdd minutes to User ID $userId");
            } catch (\Exception $e) {
                Log::error("AdMob SSV: Critical error adding minutes: " . $e->getMessage());
                return response('error', 500);
            }

            // Обновляем статус или создаем запись, если Google прислал подтверждение без нашего prepare
            if ($event) {
                DB::table('ad_reward_events')
                    ->where('id', $event->id)
                    ->update([
                        'status'      => 'granted',
                        'ad_unit_id'  => $adUnitId ?: $event->ad_unit_id,
                        'source'      => 'admob_ssv',
                        'updated_at'  => now(),
                    ]);
            } else {
                DB::table('ad_reward_events')->insert([
                    'user_id'    => $userId,
                    'nonce'      => $nonce,
                    'status'     => 'granted',
                    'ad_unit_id' => $adUnitId ?: null,
                    'source'     => 'admob_ssv_direct',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response('ok', 200);
        });
    }
}