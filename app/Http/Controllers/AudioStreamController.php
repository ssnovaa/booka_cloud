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
    /**
     * Стрімінг HLS контенту (плейлист + сегменти) із захистом.
     * Підтримує авторизацію через Bearer заголовок або параметр ?token= у URL.
     */
    public function stream(Request $request, $id, $file = null)
    {
        // 1. --- Авторизація ---
        // Пріоритет заголовку Bearer, якщо його немає — беремо з query string
        $token = $request->bearerToken() ?? $request->query('token');

        if ($token) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                if ($pat->tokenable) {
                    // Авторизуємо користувача для поточної сесії запиту
                    Auth::login($pat->tokenable);
                }
            }
        }

        // 2. --- Шукаємо главу ---
        /** @var AChapter|null $chapter */
        $chapter = AChapter::find($id);
        if (!$chapter) {
            abort(404, 'Глава не знайдена');
        }

        // 3. --- ЛОГІКА ЗАХИСТУ ---
        // Перевірка: перша глава безкоштовна, решта — тільки для авторизованих
        $firstChapter = AChapter::where('a_book_id', $chapter->a_book_id)
            ->orderBy('order')
            ->first();

        // Якщо це не перша глава і користувач не авторизований — доступ заборонено
        if (optional($firstChapter)->id !== (int)$id && !Auth::check()) {
            abort(403, 'Доступ тільки для зареєстрованих користувачів. Будь ласка, увійдіть.');
        }

        // 4. --- ВИЗНАЧЕННЯ ФАЙЛА ДЛЯ ВИДАЧІ ---
        // audio_path в базі тепер веде на index.m3u8
        // Базова папка в R2: audio/hls/{book_id}/{chapter_id}
        $basePath = dirname($chapter->audio_path); 
        
        // Якщо $file порожній — користувач запитує плейлист (index.m3u8)
        // Якщо $file має назву (наприклад, seg_001.ts) — видаємо конкретний сегмент
        $requestedFile = $file ?: basename($chapter->audio_path); 
        $fullPath = $basePath . '/' . $requestedFile;

        $disk = Storage::disk('s3_private');

        if (!$disk->exists($fullPath)) {
            Log::error("HLS файл не знайдено в приватній хмарі R2: " . $fullPath);
            abort(404, 'Файл не знайдено');
        }

        // 5. --- ВІДДАЧА КОНТЕНТУ ЧЕРЕЗ ПОТІК ---
        $fileSize = $disk->size($fullPath);
        $mimeType = $this->getMimeType($requestedFile);

        $headers = [
            'Content-Type'   => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges'  => 'bytes',
        ];

        // Забороняємо кешування для файлу плейлиста, щоб токени перевірялися щоразу
        if (str_ends_with($requestedFile, '.m3u8')) {
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        }

        return response()->stream(function () use ($disk, $fullPath) {
            $stream = $disk->readStream($fullPath);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Визначення MIME-типу для HLS файлів (плейлисти та сегменти)
     */
    private function getMimeType($filename)
    {
        if (str_ends_with($filename, '.m3u8')) {
            return 'application/x-mpegURL';
        }
        if (str_ends_with($filename, '.ts')) {
            return 'video/MP2T'; 
        }
        // Для старих файлів або інших типів
        return 'audio/mpeg';
    }
}