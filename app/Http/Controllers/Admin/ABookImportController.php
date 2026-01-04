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
            $currentProgress = Cache::get($progressKey);

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

        return view('admin.abooks.bulk_upload', compact('importList', 'activeImport'));
    }

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

    public function checkProgress(Request $request)
    {
        $path = $request->input('path');
        $key = 'import_progress_' . md5($path);
        
        $progress = Cache::get($key, 0);

        // 🔥 ЛОГ ДЛЯ ВІДЛАДКИ
        // Це дозволить побачити в /api/read-logs-secret-777, чи приходять запити і що вони бачать
        Log::info("WEB [CheckProgress]: Ключ '{$key}'. Отримано з кешу: " . json_encode($progress));

        return response()->json(['progress' => $progress]);
    }

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