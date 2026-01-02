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
     * Стрімінг HLS контенту (плейлист + сегменти) із захистом
     */
    public function stream(Request $request, $id, $file = null)
    {
        // 1. --- Авторизація (Ваш оригінальний код) ---
        if ($token = $request->bearerToken()) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                if ($pat->tokenable) {
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

        // 3. --- ВАША ЛОГІКА ЗАХИСТУ ---
        // Перевірка: перша глава безкоштовна, решта — тільки для авторизованих
        $firstChapter = AChapter::where('a_book_id', $chapter->a_book_id)
            ->orderBy('order')
            ->first();

        if (optional($firstChapter)->id !== (int)$id && !Auth::check()) {
            abort(403, 'Доступ тільки для зареєстрованих користувачів');
        }

        // 4. --- ВИЗНАЧЕННЯ ФАЙЛА ДЛЯ ВИДАЧІ ---
        // Якщо $file порожній — користувач запитує плейлист (index.m3u8)
        // Якщо $file має назву (наприклад, seg_001.ts) — видаємо сегмент
        
        $basePath = dirname($chapter->audio_path); // Папка в R2: audio/hls/{book_id}/{chapter_id}
        $requestedFile = $file ?: basename($chapter->audio_path); // За замовчуванням index.m3u8
        $fullPath = $basePath . '/' . $requestedFile;

        $disk = Storage::disk('s3_private');

        if (!$disk->exists($fullPath)) {
            Log::error("HLS файл не знайдено в R2: " . $fullPath);
            abort(404, 'Файл не знайдено');
        }

        // 5. --- ВІДДАЧА КОНТЕНТУ ---
        $fileSize = $disk->size($fullPath);
        $mimeType = $this->getMimeType($requestedFile);

        $headers = [
            'Content-Type'   => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges'  => 'bytes',
        ];

        // Якщо це плейлист (.m3u8), ми можемо додати кешування, але для захисту краще без нього
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
     * Визначення MIME-типу для HLS файлів
     */
    private function getMimeType($filename)
    {
        if (str_ends_with($filename, '.m3u8')) {
            return 'application/x-mpegURL';
        }
        if (str_ends_with($filename, '.ts')) {
            return 'video/MP2T'; // Стандартний тип для сегментів
        }
        return 'audio/mpeg';
    }
}