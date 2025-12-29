<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AChapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

class AudioStreamController extends Controller
{
    public function stream(Request $request, $id)
    {
        // 1. --- Авторизация ---
        if ($token = $request->bearerToken()) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                if ($pat->tokenable) {
                    Auth::login($pat->tokenable);
                }
            }
        }

        // 2. --- Ищем главу ---
        /** @var AChapter|null $chapter */
        $chapter = AChapter::find($id);
        if (!$chapter) {
            abort(404, 'Глава не найдена');
        }

        // 3. --- Логика доступа (ИСПРАВЛЕНО) ---
        // Находим самый маленький номер порядка (order) для этой книги
        $minOrder = AChapter::where('a_book_id', $chapter->a_book_id)->min('order');

        // Если у текущей главы порядок больше минимального — значит это не первая глава.
        // И если пользователь не вошел в систему — блокируем.
        if ($chapter->order > $minOrder && !Auth::check()) {
            abort(403, 'Доступ только для зарегистрированных пользователей');
        }

        // 4. --- Генерация ссылки R2 ---
        $filePath = ltrim($chapter->audio_path, '/\\');

        try {
            // Генерируем ссылку на 2 часа
            $url = Storage::disk('s3_private')->temporaryUrl(
                $filePath,
                now()->addMinutes(120)
            );
        } catch (\Exception $e) {
            Log::error("Ошибка генерации ссылки S3: " . $e->getMessage());
            abort(500, 'Ошибка доступа к хранилищу');
        }

        // 5. --- Редирект на Cloudflare ---
        return redirect($url);
    }
}