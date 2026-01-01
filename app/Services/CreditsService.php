<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CreditsService
{
    /**
     * Отримати залишок секунд користувача.
     */
    public function getSeconds(int $userId): int
    {
        $row = DB::table('listen_credits')->where('user_id', $userId)->first();
        if (!$row) {
            return 0;
        }

        // 0 — валидное значение; NULL трактуем как 0.
        return (int) ($row->seconds_left ?? 0);
    }

    /**
     * Встановити конкретну кількість секунд (адмін-функція).
     */
    public function setSeconds(int $userId, int $seconds): void
    {
        $sec = max(0, (int) $seconds);

        DB::transaction(function () use ($userId, $sec) {
            $row = DB::table('listen_credits')
                ->lockForUpdate()
                ->where('user_id', $userId)
                ->first();

            if (!$row) {
                DB::table('listen_credits')->insert([
                    'user_id'      => $userId,
                    'seconds_left' => $sec,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                return;
            }

            DB::table('listen_credits')
                ->where('id', $row->id)
                ->update([
                    'seconds_left' => $sec,
                    'updated_at'   => now(),
                ]);
        });
    }

    /**
     * Нарахувати хвилини (конвертуємо в секунди).
     */
    public function addMinutes(int $userId, int $minutes): void
    {
        $m = max(0, (int) $minutes);
        if ($m === 0) return;

        $addSec = $m * 60;

        DB::transaction(function () use ($userId, $addSec) {
            $row = DB::table('listen_credits')
                ->lockForUpdate()
                ->where('user_id', $userId)
                ->first();

            if (!$row) {
                DB::table('listen_credits')->insert([
                    'user_id'      => $userId,
                    'seconds_left' => $addSec,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                return;
            }

            $currentSeconds = (int) ($row->seconds_left ?? 0);

            DB::table('listen_credits')
                ->where('id', $row->id)
                ->update([
                    'seconds_left' => $currentSeconds + $addSec,
                    'updated_at'   => now(),
                ]);
        });
    }

    /**
     * Нарахувати секунди напрямую.
     */
    public function addSeconds(int $userId, int $addSeconds): void
    {
        $delta = max(0, (int) $addSeconds);
        if ($delta === 0) return;

        DB::transaction(function () use ($userId, $delta) {
            $row = DB::table('listen_credits')
                ->lockForUpdate()
                ->where('user_id', $userId)
                ->first();

            if (!$row) {
                DB::table('listen_credits')->insert([
                    'user_id'      => $userId,
                    'seconds_left' => $delta,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                return;
            }

            $currentSeconds = (int) ($row->seconds_left ?? 0);

            DB::table('listen_credits')
                ->where('id', $row->id)
                ->update([
                    'seconds_left' => $currentSeconds + $delta,
                    'updated_at'   => now(),
                ]);
        });
    }

    /**
     * Списання секунд (атомарно). Повертає [spent, remaining].
     */
    public function consumeSeconds(int $userId, int $requestedSeconds, int $perRequestMax = 300): array
    {
        $req   = max(0, (int) $requestedSeconds);
        $cap   = max(1, (int) $perRequestMax);
        $delta = min($req, $cap);

        if ($delta === 0) {
            return [0, $this->getSeconds($userId)];
        }

        return DB::transaction(function () use ($userId, $delta) {
            $row = DB::table('listen_credits')
                ->lockForUpdate()
                ->where('user_id', $userId)
                ->first();

            if (!$row) {
                return [0, 0];
            }

            $current = (int) ($row->seconds_left ?? 0);
            if ($current <= 0) {
                return [0, 0];
            }

            $spent  = min($delta, $current);
            $remain = $current - $spent;

            DB::table('listen_credits')
                ->where('id', $row->id)
                ->update([
                    'seconds_left' => $remain,
                    'updated_at'   => now(),
                ]);

            return [$spent, $remain];
        });
    }
}