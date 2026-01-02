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
     * Гібридний стрімінг: HLS для нових книг, MP3 для старих.
     */
    public function stream(Request $request, $id, $file = null)
    {
        // 1. --- Авторизація (Bearer або URL-токен) ---
        $token = $request->bearerToken() ?? $request->query('token');

        if ($token) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                if ($pat->tokenable) {
                    Auth::login($pat->tokenable);
                }
            }
        }

        // 2. --- Пошук глави ---
        /** @var AChapter|null $chapter */
        $chapter = AChapter::find($id);
        if (!$chapter) {
            abort(404, 'Глава не знайдена');
        }

        // 3. --- Захист (перша глава безкоштовно) ---
        $firstChapter = AChapter::where('a_book_id', $chapter->a_book_id)
            ->orderBy('order')
            ->first();

        if (optional($firstChapter)->id !== (int)$id && !Auth::check()) {
            abort(403, 'Доступ заборонено');
        }

        $disk = Storage::disk('s3_private');
        $requestedFile = $file;
        $fullPath = "";

        // 4. --- ЛОГІКА ГІБРИДНОГО ВИБОРУ ФАЙЛА ---
        if ($requestedFile === null) {
            // Прямий запит (старий стиль: /audio/123)
            $fullPath = $chapter->audio_path;
            $requestedFile = basename($fullPath);
        } else {
            // Запит з файлом (новий стиль: /audio/123/index.m3u8 або seg_001.ts)
            $basePath = dirname($chapter->audio_path);
            $fullPath = $basePath . '/' . $requestedFile;

            // 🔥 РОЗУМНИЙ ФОЛБЕК ДЛЯ СТАРИХ ФАЙЛІВ:
            // Якщо додаток просить index.m3u8, але його немає в папці, 
            // а в базі шлях веде до .mp3 — віддаємо оригінальний MP3.
            if ($requestedFile === 'index.m3u8' && !$disk->exists($fullPath)) {
                if (str_ends_with($chapter->audio_path, '.mp3')) {
                    $fullPath = $chapter->audio_path;
                    $requestedFile = basename($fullPath);
                }
            }
        }

        if (!$disk->exists($fullPath)) {
            abort(404, 'Аудіофайл не знайдено');
        }

        // 5. --- Віддача контенту ---
        $headers = [
            'Content-Type'   => $this->getMimeType($requestedFile),
            'Content-Length' => $disk->size($fullPath),
            'Accept-Ranges'  => 'bytes',
        ];

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

    private function getMimeType($filename)
    {
        if (str_ends_with($filename, '.m3u8')) return 'application/x-mpegURL';
        if (str_ends_with($filename, '.ts'))   return 'video/MP2T';
        return 'audio/mpeg'; // Для .mp3
    }
}