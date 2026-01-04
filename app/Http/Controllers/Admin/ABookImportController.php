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

            $progressKey = 'import_progress_' . md5($bookPath);
            
            // 🔥 ЧИТАЄМО ПРИМУСОВО З БАЗИ
            $cachedData = Cache::store('database')->get($progressKey);

            $progress = 0;
            if ($cachedData !== null) {
                if (is_array($cachedData)) {
                    $progress = $cachedData['percent'] ?? 0;
                } elseif (is_numeric($cachedData)) {
                    $progress = $cachedData;
                }
            }

            if ($progress > 0 && $progress < 100) {
                $activeImport = [
                    'path' => $bookPath,
                    'progress' => $progress
                ];
            }

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

    public function import(Request $request)
    {
        $folderPath = $request->input('folder_path');

        if (!$folderPath) {
            return back()->with('error', 'Шлях до папки порожній.');
        }

        // 🔥 ПЕРЕВІРКА: ЧИ ВЖЕ ЙДЕ ІМПОРТ?
        $progressKey = 'import_progress_' . md5($folderPath);
        $existing = Cache::store('database')->get($progressKey);
        
        $progress = 0;
        if (is_array($existing)) $progress = $existing['percent'] ?? 0;
        elseif (is_numeric($existing)) $progress = $existing;

        if ($progress > 0 && $progress < 100) {
            return back()->with('error', 'Імпорт цієї книги вже виконується! Зачекайте завершення.');
        }

        ProcessBookImport::dispatch($folderPath);

        return back()->with([
            'success' => "Імпорт розпочато у фоновому режимі.",
            'import_path' => $folderPath 
        ]);
    }

    public function checkProgress(Request $request)
    {
        $path = $request->input('path');
        $key = 'import_progress_' . md5($path);
        
        // 🔥 ЧИТАЄМО ПРИМУСОВО З БАЗИ
        $data = Cache::store('database')->get($key);
        
        $progress = 0;
        $lastUpdate = time();
        $status = 'processing';

        if (is_array($data)) {
            $progress = $data['percent'] ?? 0;
            $lastUpdate = $data['time'] ?? time();
        } elseif (is_numeric($data)) {
            $progress = $data;
            $lastUpdate = time(); 
        }

        // 1.5 хвилини тиші = stuck
        if ($progress < 100 && (time() - $lastUpdate > 90)) {
            $status = 'stuck';
        }

        if ($progress == -1) {
            $status = 'error';
        }

        return response()->json([
            'progress' => $progress,
            'status' => $status,
            'last_update_diff' => time() - $lastUpdate
        ]);
    }

    public function cancelImport(Request $request)
    {
        $folderPath = $request->input('folder_path');
        
        if ($folderPath) {
            $key = 'import_cancel_' . md5($folderPath);
            // 🔥 ПИШЕМО ВІДМІНУ В БАЗУ
            Cache::store('database')->put($key, true, 120); 
            Log::info("Користувач запросив скасування імпорту для: {$folderPath}");
        }

        return response()->json(['status' => 'cancelled']);
    }
}