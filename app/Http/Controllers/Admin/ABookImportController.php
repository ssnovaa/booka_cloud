<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ABook;
use App\Models\AChapter;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use getID3;

class ABookImportController extends Controller
{
    /**
     * Показати список папок у 'incoming' (Формат: Автор_Назва)
     */
    public function bulkUploadView()
    {
        $disk = Storage::disk('s3_private');
        
        // Создаем папку, если её нет
        if (!$disk->exists('incoming')) {
            $disk->makeDirectory('incoming');
        }

        $bookDirs = $disk->directories('incoming');
        $importList = [];

        foreach ($bookDirs as $bookPath) {
            $folderName = basename($bookPath);
            // Разделяем имя папки по "_"
            $parts = explode('_', $folderName, 2);
            
            if (count($parts) === 2) {
                $authorName = trim($parts[0]);
                $bookTitle = trim($parts[1]);
            } else {
                $authorName = 'Невідомий';
                $bookTitle = trim($folderName);
            }

            // Получаем список всех файлов
            $allFiles = $disk->files($bookPath);

            // Считаем только MP3
            $mp3Count = collect($allFiles)
                ->filter(fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3')
                ->count();
            
            // Ищем обложку (jpg, jpeg, png)
            $hasCover = collect($allFiles)
                ->contains(fn($f) => in_array(Str::lower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']));

            if ($mp3Count > 0) {
                $importList[] = [
                    'author'   => $authorName,
                    'title'    => $bookTitle,
                    'path'     => $bookPath,
                    'files'    => $mp3Count,
                    'hasCover' => $hasCover
                ];
            }
        }

        return view('admin.abooks.bulk_upload', compact('importList'));
    }

    /**
     * Импорт выбранной папки
     */
    public function import(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $folderPath = $request->input('folder_path');
        $diskPrivate = Storage::disk('s3_private');
        $diskPublic = Storage::disk('s3');

        if (!$folderPath || !$diskPrivate->exists($folderPath)) {
            return back()->with('error', 'Папку не знайдено.');
        }

        // 1. Разбор имени
        $folderName = basename($folderPath);
        $parts = explode('_', $folderName, 2);

        if (count($parts) === 2) {
            $authorName = trim($parts[0]);
            $bookTitle = trim($parts[1]);
        } else {
            $authorName = 'Невідомий';
            $bookTitle = trim($folderName);
        }

        Log::info("📥 Імпорт start: $bookTitle ($authorName)");

        // 2. Ищем и обрабатываем обложку
        $allFiles = $diskPrivate->files($folderPath);
        $coverUrl = null;
        $thumbUrl = null;

        $imageFile = collect($allFiles)->first(fn($f) => in_array(Str::lower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']));

        if ($imageFile) {
            try {
                $tempCoverPath = storage_path('app/temp_import/cover_' . time() . '.' . pathinfo($imageFile, PATHINFO_EXTENSION));
                if (!file_exists(dirname($tempCoverPath))) mkdir(dirname($tempCoverPath), 0777, true);
                
                file_put_contents($tempCoverPath, $diskPrivate->get($imageFile));

                $s3CoverName = 'covers/' . time() . '_' . basename($imageFile);
                $s3ThumbName = 'covers/thumb_' . basename($s3CoverName);

                // Загружаем на публичный диск
                $diskPublic->put($s3CoverName, fopen($tempCoverPath, 'r+'), 'public');

                // Делаем миниатюру
                $image = Image::read($tempCoverPath)->cover(200, 300);
                $diskPublic->put($s3ThumbName, (string) $image->toJpeg(80), 'public');

                $coverUrl = $s3CoverName;
                $thumbUrl = $s3ThumbName;

                @unlink($tempCoverPath);
            } catch (\Exception $e) {
                Log::error("Cover error: " . $e->getMessage());
            }
        }

        // 3. Создаем книгу и автора
        $author = Author::firstOrCreate(['name' => $authorName]);
        
        $book = ABook::create([
            'title'       => $bookTitle,
            'author_id'   => $author->id,
            'description' => 'Імпортовано з S3/FTP',
            'cover_url'   => $coverUrl,
            'thumb_url'   => $thumbUrl,
        ]);

        // 4. Обработка MP3 -> HLS
        $mp3Files = array_filter($allFiles, fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3');

        // Сортировка (Натуральная: 1, 2, 10...)
        usort($mp3Files, function($a, $b) {
            return strnatcmp(basename($a), basename($b));
        });

        $getID3 = new getID3();
        $totalSeconds = 0;
        $order = 1;

        foreach ($mp3Files as $file) {
            $fileName = basename($file);
            
            // Качаем временно на сервер
            $localTempPath = storage_path("app/temp_import/{$book->id}_{$order}.mp3");
            if (!file_exists(dirname($localTempPath))) mkdir(dirname($localTempPath), 0777, true);
            file_put_contents($localTempPath, $diskPrivate->get($file));

            // Длительность
            $fileInfo = $getID3->analyze($localTempPath);
            $duration = (int) round($fileInfo['playtime_seconds'] ?? 0);
            $totalSeconds += $duration;

            // Конвертация в HLS
            $localHlsFolder = storage_path("app/temp_hls/{$book->id}/{$order}");
            if (!file_exists($localHlsFolder)) mkdir($localHlsFolder, 0777, true);

            $playlistName = "index.m3u8";
            // Команда ffmpeg
            $cmd = "ffmpeg -i " . escapeshellarg($localTempPath) . " -c:a libmp3lame -b:a 128k -map 0:0 -f hls -hls_time 10 -hls_list_size 0 -threads 0 -hls_segment_filename " . escapeshellarg("{$localHlsFolder}/seg_%03d.ts") . " " . escapeshellarg("{$localHlsFolder}/{$playlistName}") . " 2>&1";
            shell_exec($cmd);

            // Заливаем сегменты обратно в R2
            $cloudFolder = "audio/hls/{$book->id}/{$order}";
            if (file_exists("{$localHlsFolder}/{$playlistName}")) {
                foreach (scandir($localHlsFolder) as $hlsFile) {
                    if ($hlsFile === '.' || $hlsFile === '..') continue;
                    $diskPrivate->put("{$cloudFolder}/{$hlsFile}", fopen("{$localHlsFolder}/{$hlsFile}", 'r+'));
                }

                AChapter::create([
                    'a_book_id'  => $book->id,
                    'title'      => pathinfo($fileName, PATHINFO_FILENAME),
                    'order'      => $order,
                    'audio_path' => "{$cloudFolder}/{$playlistName}",
                    'duration'   => $duration,
                ]);
            }

            // Чистим мусор
            @unlink($localTempPath);
            array_map('unlink', glob("{$localHlsFolder}/*.*"));
            @rmdir($localHlsFolder);
            
            $order++;
        }

        $book->update(['duration' => (int) round($totalSeconds / 60)]);

        return back()->with('success', "Книга '{$bookTitle}' успішно імпортована!");
    }
}