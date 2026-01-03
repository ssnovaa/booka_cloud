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
use App\Jobs\ProcessBookImport; // 🔥 Добавили импорт задачи

class ABookImportController extends Controller
{
    public function bulkUploadView()
    {
        Log::info("777_DEBUG: [View] Scanning 'incoming' folder on S3...");

        $disk = Storage::disk('s3_private');
        
        if (!$disk->exists('incoming')) {
            $disk->makeDirectory('incoming');
        }

        $bookDirs = $disk->directories('incoming');
        Log::info("777_DEBUG: NAMES: " . implode(', ', $bookDirs));

        $importList = [];

        foreach ($bookDirs as $bookPath) {
            $folderName = basename($bookPath);

            // Игнорируем саму папку incoming, если она попала в список
            if ($folderName === 'incoming') continue;

            $parts = explode('_', $folderName, 2);
            
            if (count($parts) === 2) {
                $authorName = trim($parts[0]);
                $bookTitle = trim($parts[1]);
            } else {
                $authorName = 'Невідомий';
                $bookTitle = trim($folderName);
            }

            // 🔥 ВИПРАВЛЕННЯ: Шукаємо файли рекурсивно (allFiles замість files)
            // Це дозволяє бачити MP3 навіть у підпапці "фаилы"
            $allFiles = $disk->allFiles($bookPath);

            Log::info("777_DEBUG: Checking $folderName. Found " . count($allFiles) . " files.");

            $mp3Count = collect($allFiles)
                ->filter(fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3')
                ->count();
            
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
            } else {
                Log::warning("777_DEBUG: Folder $folderName skipped (0 MP3 found).");
            }
        }

        return view('admin.abooks.bulk_upload', compact('importList'));
    }

    public function import(Request $request)
    {
        $folderPath = $request->input('folder_path');

        if (!$folderPath) {
            return back()->with('error', 'Шлях до папки порожній.');
        }

        // 🔥 ВЕСЬ ТЯЖЕЛЫЙ КОД ТЕПЕРЬ ЖИВЕТ ВНУТРИ ЭТОЙ КОМАНДЫ:
        ProcessBookImport::dispatch($folderPath);

        return back()->with('success', "Імпорт розпочато у фоновому режимі. Можете закрити сторінку, сервер все дороблять сам.");
    }
}