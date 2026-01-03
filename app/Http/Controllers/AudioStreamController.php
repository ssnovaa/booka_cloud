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
     * Підтримує авторизацію через заголовок Bearer або параметр ?token= у URL.
     */
    public function stream(Request $request, $id, $file = null)
    {
        // 🔥 777 ЛОГ: Початок запиту
        Log::info("777_DEBUG: Request Start. ID: $id, File: " . ($file ?? 'Playlist/MP3'));

        // 1. --- Авторизація (Bearer заголовок або URL-токен) ---
        $token = $request->bearerToken() ?? $request->query('token');

        if ($token) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                if ($pat->tokenable) {
                    // Тимчасово авторизуємо користувача для поточної перевірки
                    Auth::login($pat->tokenable);
                    Log::info("777_DEBUG: User ID " . $pat->tokenable->id . " authenticated via token.");
                }
            } else {
                Log::warning("777_DEBUG: Token present but NOT VALID.");
            }
        }

        // 2. --- Пошук глави ---
        /** @var AChapter|null $chapter */
        $chapter = AChapter::find($id);
        if (!$chapter) {
            Log::error("777_DEBUG: Chapter $id not found in DB.");
            abort(404, 'Глава не знайдена');
        }

        // 3. --- Логіка захисту (перша глава безкоштовна) ---
        
        // Дозволяємо доступ до сегментів (.ts) без перевірки токена, 
        // оскільки плейлист (.m3u8) уже захищений.
        $isSegment = $file && str_ends_with($file, '.ts');

        if (!$isSegment) {
            $firstChapter = AChapter::where('a_book_id', $chapter->a_book_id)
                ->orderBy('order')
                ->first();

            // Якщо це не перша глава і користувач не зайшов у профіль — доступ заборонено
            if (optional($firstChapter)->id !== (int)$id && !Auth::check()) {
                Log::warning("777_DEBUG: Access DENIED for Chapter $id (Unauthorized).");
                abort(403, 'Доступ дозволено тільки авторизованим користувачам');
            }
            Log::info("777_DEBUG: Access GRANTED (Playlist or First Chapter).");
        } else {
            Log::info("777_DEBUG: Segment .ts requested. Skipping token check.");
        }

        $disk = Storage::disk('s3_private');
        $requestedFile = $file;
        $fullPath = "";

        // 4. --- ЛОГІКА ВИБОРУ ФАЙЛА (Гібридний режим) ---
        if ($requestedFile === null) {
            // Прямий запит (старий стиль: /audio/123)
            $fullPath = $chapter->audio_path;
            $requestedFile = basename($fullPath);
        } else {
            // Запит з файлом (новий стиль: /audio/123/index.m3u8 або seg_001.ts)
            $basePath = dirname($chapter->audio_path);
            $fullPath = $basePath . '/' . $requestedFile;

            // РОЗУМНИЙ ФОЛБЕК ДЛЯ СТАРИХ КНИГ:
            if ($requestedFile === 'index.m3u8' && !$disk->exists($fullPath)) {
                if (str_ends_with($chapter->audio_path, '.mp3')) {
                    $fullPath = $chapter->audio_path;
                    $requestedFile = basename($fullPath);
                    Log::info("777_DEBUG: Fallback to MP3 for old book.");
                }
            }
        }

        // Кінцева перевірка наявності файлу в R2
        if (!$disk->exists($fullPath)) {
            Log::error("777_DEBUG: FILE NOT FOUND IN R2: $fullPath");
            abort(404, 'Аудіофайл не знайдено');
        }

        // 5. --- Формування відповіді ---
        $fileSize = $disk->size($fullPath);
        $mimeType = $this->getMimeType($requestedFile);

        $headers = [
            'Content-Type'   => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges'  => 'bytes',
        ];

        // Забороняємо кешування плейлиста
        if (str_ends_with($requestedFile, '.m3u8')) {
            $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
            
            // 🔥 777 ЛОГ: Виводимо вміст плейлиста, щоб побачити шляхи до сегментів
            try {
                $content = $disk->get($fullPath);
                Log::info("777_DEBUG: CONTENT OF M3U8:\n" . $content);
            } catch (\Exception $e) {
                Log::error("777_DEBUG: Failed to read m3u8 content: " . $e->getMessage());
            }
        }

        Log::info("777_DEBUG: Streaming file. Path: $fullPath, MIME: $mimeType, Size: $fileSize");

        // Потокова віддача файлу
        return response()->stream(function () use ($disk, $fullPath) {
            $stream = $disk->readStream($fullPath);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    /**
     * Визначення MIME-типу за розширенням файлу
     */
    private function getMimeType($filename)
    {
        if (str_ends_with($filename, '.m3u8')) {
            return 'application/x-mpegURL';
        }
        if (str_ends_with($filename, '.ts')) {
            return 'video/MP2T';
        }
        // За замовчуванням вважаємо за MP3
        return 'audio/mpeg';
    }
}