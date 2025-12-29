<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AChapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // <--- Важно: Добавили фасад Storage
use Laravel\Sanctum\PersonalAccessToken;

class AudioStreamController extends Controller
{
    public function stream(Request $request, $id)
    {
        // 1. --- Авторизация (Ваш код) ---
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

        // 3. --- Логика доступа (Ваш код) ---
        // Первая глава книги (демо)
        $firstChapter = AChapter::where('a_book_id', $chapter->a_book_id)
            ->orderBy('order')
            ->first();

        // Доступ: не первая глава → только авторизованным
        if (optional($firstChapter)->id !== $chapter->id && !Auth::check()) {
            abort(403, 'Доступ только для зарегистрированных пользователей');
        }

        // 4. --- ГЛАВНОЕ ИЗМЕНЕНИЕ: Генерируем ссылку на Cloudflare ---
        
        // Очищаем путь от лишних слешей в начале, чтобы он совпадал с путем в бакете
        $filePath = ltrim($chapter->audio_path, '/\\');

        try {
            // Используем диск 's3_private', который мы настроили в config/filesystems.php
            // Ссылка будет жить 120 минут (2 часа)
            $url = Storage::disk('s3_private')->temporaryUrl(
                $filePath,
                now()->addMinutes(120)
            );
        } catch (\Exception $e) {
            // Если забыли добавить настройки в .env или ошибка связи
            Log::error("Ошибка генерации ссылки S3: " . $e->getMessage());
            abort(500, 'Ошибка доступа к хранилищу');
        }

        // 5. --- Перенаправляем плеер на эту временную ссылку ---
        return redirect($url);
    }
}