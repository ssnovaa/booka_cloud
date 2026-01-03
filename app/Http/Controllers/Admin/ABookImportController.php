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
use getID3;

class ABookImportController extends Controller
{
    /**
     * Показати список папок у 'incoming' на R2
     */
    public function bulkUploadView()
    {
        $disk = Storage::disk('s3_private');
        
        // Переконаємось, що папка incoming існує
        if (!$disk->exists('incoming')) {
            $disk->makeDirectory('incoming');
        }

        // Отримуємо список авторів (папок)
        $authorDirs = $disk->directories('incoming');
        $importList = [];

        foreach ($authorDirs as $authorPath) {
            $authorName = basename($authorPath);
            
            // Скануємо книги всередині автора
            $bookDirs = $disk->directories($authorPath);
            
            foreach ($bookDirs as $bookPath) {
                $bookTitle = basename($bookPath);
                
                // Рахуємо MP3 файли
                $files = collect($disk->files($bookPath))
                    ->filter(fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3')
                    ->count();

                if ($files > 0) {
                    $importList[] = [
                        'author' => $authorName,
                        'title'  => $bookTitle,
                        'path'   => $bookPath, // Повний шлях: incoming/Author/Book
                        'files'  => $files
                    ];
                }
            }
        }

        return view('admin.abooks.bulk_upload', compact('importList'));
    }

    /**
     * Імпорт обраної папки
     */
    public function import(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $folderPath = $request->input('folder_path');
        $disk = Storage::disk('s3_private');

        if (!$folderPath || !$disk->exists($folderPath)) {
            return back()->with('error', 'Папку не знайдено або її вже імпортовано.');
        }

        // 1. Розбір шляху
        $parts = explode('/', $folderPath);
        $bookTitle = end($parts);
        $authorName = prev($parts);

        Log::info("📥 Початок імпорту: $bookTitle (Автор: $authorName)");

        // 2. Створення в БД
        $author = Author::firstOrCreate(['name' => $authorName]);
        
        $book = ABook::create([
            'title'       => $bookTitle,
            'author_id'   => $author->id,
            'description' => 'Імпортовано автоматично з R2',
            'cover_url'   => null, // Поки без обкладинки
        ]);

        // 3. Обробка файлів
        $allFiles = $disk->files($folderPath);
        $mp3Files = array_filter($allFiles, fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3');

        // Сортуємо (01.mp3, 02.mp3...)
        usort($mp3Files, function($a, $b) {
            return strnatcmp(basename($a), basename($b));
        });

        $getID3 = new getID3();
        $totalSeconds = 0;
        $order = 1;

        foreach ($mp3Files as $file) {
            $fileName = basename($file);
            
            // Завантажуємо на сервер для обробки
            $localTempPath = storage_path("app/temp_import/{$book->id}_{$order}.mp3");
            if (!file_exists(dirname($localTempPath))) mkdir(dirname($localTempPath), 0777, true);
            
            file_put_contents($localTempPath, $disk->get($file));

            // Тривалість
            $fileInfo = $getID3->analyze($localTempPath);
            $duration = (int) round($fileInfo['playtime_seconds'] ?? 0);
            $totalSeconds += $duration;

            // HLS Конвертація
            $localHlsFolder = storage_path("app/temp_hls/{$book->id}/{$order}");
            if (!file_exists($localHlsFolder)) mkdir($localHlsFolder, 0777, true);

            $playlistName = "index.m3u8";
            // ffmpeg cmd
            $cmd = "ffmpeg -i " . escapeshellarg($localTempPath) . " -c:a libmp3lame -b:a 128k -map 0:0 -f hls -hls_time 10 -hls_list_size 0 -threads 0 -hls_segment_filename " . escapeshellarg("{$localHlsFolder}/seg_%03d.ts") . " " . escapeshellarg("{$localHlsFolder}/{$playlistName}") . " 2>&1";
            shell_exec($cmd);

            // Завантаження HLS в R2 (audio/hls/...)
            $cloudFolder = "audio/hls/{$book->id}/{$order}";
            $filesInHls = scandir($localHlsFolder);

            foreach ($filesInHls as $hlsFile) {
                if ($hlsFile === '.' || $hlsFile === '..') continue;
                $disk->put("{$cloudFolder}/{$hlsFile}", fopen("{$localHlsFolder}/{$hlsFile}", 'r+'));
            }

            // Запис глави
            AChapter::create([
                'a_book_id'  => $book->id,
                'title'      => pathinfo($fileName, PATHINFO_FILENAME),
                'order'      => $order,
                'audio_path' => "{$cloudFolder}/{$playlistName}",
                'duration'   => $duration,
            ]);

            // Прибирання
            @unlink($localTempPath);
            array_map('unlink', glob("{$localHlsFolder}/*.*"));
            @rmdir($localHlsFolder);
            
            $order++;
        }

        $book->update(['duration' => (int) round($totalSeconds / 60)]);

        // 4. Опціонально: видалити вихідну папку з incoming, щоб не дублювати
        // $disk->deleteDirectory($folderPath);

        return back()->with('success', "Книга '{$bookTitle}' імпортована! (ID: {$book->id})");
    }
}