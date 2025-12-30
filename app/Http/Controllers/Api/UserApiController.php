<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class UserApiController extends Controller
{
    /**
     * Профиль пользователя + актуальное «текущее прослушивание».
     */
    public function profile(Request $request)
    {
        // 1) Аутентификация пользователя
        $user = Auth::guard('sanctum')->user() ?? $request->user();

        if (!$user) {
            $bearer = $request->bearerToken();
            if ($bearer) {
                $pat = PersonalAccessToken::findToken($bearer);
                if ($pat) {
                    $user = $pat->tokenable;
                }
            }
        }

        // Helper: безопасно достать секунды из listen_credits
        $safeGetFreeSeconds = static function (?int $userId): int {
            if (!$userId || !Schema::hasTable('listen_credits')) return 0;

            $hasSeconds = Schema::hasColumn('listen_credits', 'seconds_left');
            $hasMinutes = Schema::hasColumn('listen_credits', 'minutes');

            if (!$hasSeconds && !$hasMinutes) return 0;

            try {
                $row = DB::table('listen_credits')->where('user_id', $userId)->first();
                if (!$row) return 0;

                if ($hasSeconds && isset($row->seconds_left) && $row->seconds_left !== null) {
                    return max(0, (int) $row->seconds_left);
                }

                if ($hasMinutes && isset($row->minutes) && $row->minutes !== null) {
                    return max(0, (int) $row->minutes * 60);
                }
            } catch (\Throwable $e) {
                return 0;
            }
            return 0;
        };

        // Helper для формирования чистых ссылок на R2
        $r2Base = 'https://pub-231bc7be1b7343d6b8e04d0b559c9156.r2.dev';
        $formatR2Url = function($path) use ($r2Base) {
            if (!$path) return null;
            if (str_starts_with($path, 'http')) {
                $path = str_replace('https://bookacloud-production.up.railway.app/storage/', '', $path);
                if (str_starts_with($path, 'http')) return $path;
            }
            $cleanPath = ltrim($path, '/');
            if (str_starts_with($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, 8);
            }
            return $r2Base . '/' . ltrim($cleanPath, '/');
        };

        // 2) Гость — пустой профиль
        if (!$user) {
            return response()
                ->json([
                    'id'             => null,
                    'name'           => null,
                    'email'          => null,
                    'is_paid'        => false,
                    'paid_until'     => null, 
                    'free_seconds'   => 0,
                    'free_minutes'   => 0,
                    'favorites'      => [],
                    'listened'       => [],
                    'current_listen' => null,
                    'server_time'    => now()->toIso8601String(),
                ], 200)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        // 3) Авторизованный пользователь
        $freeSeconds = $safeGetFreeSeconds((int) $user->id);
        
        // Избранные книги (без duration)
        $favorites = $user->favoriteBooks()
            ->with('author')
            ->get()
            ->map(function ($book) use ($formatR2Url) {
                return [
                    'id'          => (int) $book->id,
                    'title'       => (string) $book->title,
                    'author'      => optional($book->author)->name,
                    'cover_url'   => $formatR2Url($book->cover_url),
                    'thumb_url'   => $formatR2Url($book->thumb_url ?? $book->cover_url), 
                    'description' => (string) ($book->description ?? ""),
                ];
            })
            ->values();

        // Прослушанные книги (без duration)
        $listened = $user->listenedBooks()
            ->with('author')
            ->get()
            ->map(function ($book) use ($formatR2Url) {
                return [
                    'id'          => (int) $book->id,
                    'title'       => (string) $book->title,
                    'author'      => optional($book->author)->name,
                    'cover_url'   => $formatR2Url($book->cover_url),
                    'thumb_url'   => $formatR2Url($book->thumb_url ?? $book->cover_url),
                    'description' => (string) ($book->description ?? ""),
                ];
            })
            ->values();

        // Текущее прослушивание (без duration)
        $last = $user->listens()
            ->with(['book.author', 'chapter'])
            ->orderByDesc('updated_at')
            ->first();

        $currentListen = null;
        if ($last) {
            $currentListen = [
                'book_id'    => (int) $last->a_book_id,
                'chapter_id' => (int) $last->a_chapter_id,
                'position'   => (int) ($last->position ?? 0),
                'updated_at' => optional($last->updated_at)->toIso8601String(),
                'book'       => [
                    'id'          => (int) $last->a_book_id,
                    'title'       => optional($last->book)->title,
                    'author'      => optional(optional($last->book)->author)->name,
                    'cover_url'   => $formatR2Url(optional($last->book)->cover_url),
                    'thumb_url'   => $formatR2Url(optional($last->book)->thumb_url ?? optional($last->book)->cover_url),
                    'description' => (string) (optional($last->book)->description ?? ""),
                ],
                'chapter'    => [
                    'id'    => (int) $last->a_chapter_id,
                    'title' => optional($last->chapter)->title,
                ],
            ];
        }

        return response()
            ->json([
                'id'             => (int) $user->id,
                'name'           => (string) $user->name,
                'email'          => (string) $user->email,
                'is_paid'        => (bool) $user->is_paid,
                'paid_until'     => $user->paid_until ? $user->paid_until->toIso8601String() : null,
                'free_seconds'   => $freeSeconds,
                'free_minutes'   => intdiv($freeSeconds, 60),
                'favorites'      => $favorites,
                'listened'       => $listened,
                'current_listen' => $currentListen,
                'server_time'    => now()->toIso8601String(),
            ], 200)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}