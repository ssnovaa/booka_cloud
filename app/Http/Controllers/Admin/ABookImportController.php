<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ProcessBookImport;

class ABookImportController extends Controller
{
    /**
     * Сторінка зі списком папок для імпорту (R2/S3)
     */
    public function bulkUploadView()
    {
        $disk = Storage::disk('s3_private');
        
        if (!$disk->exists('incoming')) {
            $disk->makeDirectory('incoming');
        }

        $bookDirs = $disk->directories('incoming');
        $importList = [];
        $activeImport = null;

        foreach ($bookDirs as $bookPath) {
            $folderName = basename($bookPath);
            if ($folderName === 'incoming') continue;

            // Генеруємо ключ прогресу
            $progressKey = 'import_progress_' . md5($bookPath);
            $cachedData = Cache::get($progressKey);

            // 🔥 ВИПРАВЛЕННЯ: Правильно читаємо дані (число або масив)
            $progress = 0;
            if ($cachedData !== null) {
                if (is_array($cachedData)) {
                    $progress = $cachedData['percent'] ?? 0;
                } elseif (is_numeric($cachedData)) {
                    $progress = $cachedData;
                }
            }

            // Якщо прогрес > 0 і < 100, значить процес активний
            if ($progress > 0 && $progress < 100) {
                $activeImport = [
                    'path' => $bookPath,
                    'progress' => $progress
                ];
            }

            // Парсимо назву та інформацію про файли
            $parts = explode('_', $folderName, 2);
            $authorName = count($parts) === 2 ? trim($parts[0]) : 'Невідомий';
            $bookTitle = count($parts) === 2 ? trim($parts[1]) : trim($folderName);

            $allFiles = $disk->allFiles($bookPath);
            $mp3Count = collect($allFiles)->filter(fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3')->count();
            $hasCover = collect($allFiles)->contains(fn($f) => in_array(Str::lower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']));

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

        return view('admin.abooks.bulk_upload', compact('importList', 'activeImport'));
    }

    /**
     * Запуск імпорту (створення Job)
     */
    public function import(Request $request)
    {
        $folderPath = $request->input('folder_path');

        if (!$folderPath) {
            return back()->with('error', 'Шлях до папки порожній.');
        }

        ProcessBookImport::dispatch($folderPath);

        return back()->with([
            'success' => "Імпорт розпочато у фоновому режимі.",
            'import_path' => $folderPath 
        ]);
    }

    /**
     * API для перевірки прогресу (викликається через JS fetch)
     */
    public function checkProgress(Request $request)
    {
        $path = $request->input('path');
        $key = 'import_progress_' . md5($path);
        
        $data = Cache::get($key);
        
        $progress = 0;
        $lastUpdate = time();
        $status = 'processing';

        // Розбираємо, що прийшло (старий формат - число, новий - масив)
        if (is_array($data)) {
            $progress = $data['percent'] ?? 0;
            $lastUpdate = $data['time'] ?? time();
        } elseif (is_numeric($data)) {
            $progress = $data;
            $lastUpdate = time(); 
        }

        // Перевірка на "зависання" (1.5 хвилини тиші)
        if ($progress < 100 && (time() - $lastUpdate > 90)) {
            $status = 'stuck';
        }

        if ($progress == -1) {
            $status = 'error';
        }

        // Логуємо для налагодження
        // Log::info("WEB [CheckProgress]: Ключ '{$key}'. Прогрес: {$progress}%. Status: {$status}");

        return response()->json([
            'progress' => $progress,
            'status' => $status,
            'last_update_diff' => time() - $lastUpdate
        ]);
    }

    /**
     * API для скасування імпорту
     */
    public function cancelImport(Request $request)
    {
        $folderPath = $request->input('folder_path');
        
        if ($folderPath) {
            $key = 'import_cancel_' . md5($folderPath);
            Cache::put($key, true, 120); 
            Log::info("Користувач запросив скасування імпорту для: {$folderPath}");
        }

        return response()->json(['status' => 'cancelled']);
    }
}