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
        // Скануємо папку 'incoming' на S3/R2
        $disk = Storage::disk('s3_private');
        
        if (!$disk->exists('incoming')) {
            $disk->makeDirectory('incoming');
        }

        $bookDirs = $disk->directories('incoming');
        $importList = [];
        
        // 🔥 ЗМІНА 1: Змінна для пошуку активного імпорту
        $activeImport = null;

        foreach ($bookDirs as $bookPath) {
            $folderName = basename($bookPath);
            if ($folderName === 'incoming') continue;

            // 🔥 ЗМІНА 2: Перевіряємо, чи йде зараз процес по цій папці
            // Генеруємо той самий ключ, що і в Job
            $progressKey = 'import_progress_' . md5($bookPath);
            $currentProgress = Cache::get($progressKey);

            // Якщо в кеші є запис і він менше 100% — значить процес йде!
            if ($currentProgress !== null && $currentProgress < 100) {
                $activeImport = [
                    'path' => $bookPath,
                    'progress' => $currentProgress
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

        // 🔥 ЗМІНА 3: Передаємо $activeImport у шаблон
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

        // Запуск фонової задачі
        ProcessBookImport::dispatch($folderPath);

        // Повертаємо 'import_path' у сесію, щоб JS знав, за ким стежити
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
        
        // Беремо значення з кешу (якщо нема — 0)
        $progress = Cache::get($key, 0);

        return response()->json(['progress' => $progress]);
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