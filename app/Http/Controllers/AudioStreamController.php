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

        // 3. --- ВАША ОРИГИНАЛЬНАЯ ЛОГИКА ЗАЩИТЫ ---
        // Ищем первую главу именно так, как было у вас
        $firstChapter = AChapter::where('a_book_id', $chapter->a_book_id)
            ->orderBy('order')
            ->first();

        // Проверка: если это не первая глава по ID и нет авторизации — 403
        if (optional($firstChapter)->id !== $chapter->id && !Auth::check()) {
            abort(403, 'Доступ только для зарегистрированных пользователей');
        }

        // 4. --- ПОЛУЧЕНИЕ ФАЙЛА ИЗ ПРИВАТНОГО ОБЛАКА ---
        $filePath = ltrim($chapter->audio_path, '/\\');
        $disk = Storage::disk('s3_private');

        if (!$disk->exists($filePath)) {
            abort(404, 'Файл не найден в хранилище');
        }

        // 5. --- СТРИМИНГ (Скрываем URL облака) ---
        // Мы не делаем редирект. Мы читаем файл из облака и отдаем его пользователю от имени сайта.
        // Это полностью эмулирует поведение "private storage" на локальном диске.
        
        $fileSize = $disk->size($filePath);
        $mimeType = $disk->mimeType($filePath) ?? 'audio/mpeg';

        $headers = [
            'Content-Type'        => $mimeType,
            'Content-Length'      => $fileSize,
            'Accept-Ranges'       => 'bytes',
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ];

        return response()->stream(function () use ($disk, $filePath) {
            // Открываем поток к облаку и передаем данные клиенту
            $stream = $disk->readStream($filePath);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}