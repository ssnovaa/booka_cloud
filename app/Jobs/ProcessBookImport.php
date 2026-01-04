<?php

namespace App\Jobs;

use App\Models\ABook;
use App\Models\AChapter;
use App\Models\Author;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use getID3;

class ProcessBookImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    protected $folderPath;
    protected $progressKey;
    protected $cancelKey;

    public function __construct($folderPath)
    {
        $this->folderPath = $folderPath;
        $this->progressKey = 'import_progress_' . md5($folderPath);
        $this->cancelKey = 'import_cancel_' . md5($folderPath);
    }

    public function handle()
    {
        // 🔥 Пишемо в консоль примусово, щоб ви побачили це в Railway
        Log::channel('stderr')->info("🚀 JOB STARTED: Починаю обробку папки: " . $this->folderPath);

        try {
            Cache::forget($this->cancelKey);

            // Перевірка підключення до дисків
            $diskPrivate = Storage::disk('s3_private');
            $diskPublic = Storage::disk('s3');

            Log::channel('stderr')->info("JOB: Диски ініціалізовано. Шукаю файли...");

            $folderName = basename($this->folderPath);
            $parts = explode('_', $folderName, 2);
            $authorName = count($parts) === 2 ? trim($parts[0]) : 'Невідомий';
            $bookTitle = count($parts) === 2 ? trim($parts[1]) : trim($folderName);

            $allFiles = $diskPrivate->allFiles($this->folderPath);
            Log::channel('stderr')->info("JOB: Знайдено файлів: " . count($allFiles));
            
            // 1. Обложка
            $coverUrl = null;
            $thumbUrl = null;
            $imageFile = collect($allFiles)->first(fn($f) => in_array(Str::lower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']));

            if ($imageFile) {
                try {
                    $tempPath = storage_path('app/temp_import/'.Str::random(10));
                    @mkdir(dirname($tempPath), 0777, true);
                    file_put_contents($tempPath, $diskPrivate->get($imageFile));
                    
                    $s3CoverName = 'covers/' . time() . '_' . basename($imageFile);
                    $diskPublic->put($s3CoverName, fopen($tempPath, 'r+'), 'public');
                    
                    $thumb = Image::read($tempPath)->cover(200, 300);
                    $s3ThumbName = 'covers/thumb_' . time() . '.jpg';
                    $diskPublic->put($s3ThumbName, (string) $thumb->toJpeg(80), 'public');

                    $coverUrl = $s3CoverName;
                    $thumbUrl = $s3ThumbName;
                    @unlink($tempPath);
                    Log::channel('stderr')->info("JOB: Обкладинка оброблена.");
                } catch (\Exception $e) { 
                    Log::channel('stderr')->error("Job Cover Error: " . $e->getMessage()); 
                }
            }

            $author = Author::firstOrCreate(['name' => $authorName]);
            $book = ABook::create([
                'title' => $bookTitle,
                'author_id' => $author->id,
                'description' => 'Імпортовано в фоні',
                'cover_url' => $coverUrl,
                'thumb_url' => $thumbUrl,
            ]);

            Log::channel('stderr')->info("JOB: Книгу створено в БД (ID: {$book->id})");

            $mp3Files = array_filter($allFiles, fn($f) => Str::lower(pathinfo($f, PATHINFO_EXTENSION)) === 'mp3');
            usort($mp3Files, fn($a, $b) => strnatcmp(basename($a), basename($b)));

            $getID3 = new getID3();
            $totalSeconds = 0;
            $order = 1;
            $totalFiles = count($mp3Files);

            foreach ($mp3Files as $file) {
                if (Cache::has($this->cancelKey)) {
                    Log::channel('stderr')->info("🛑 Import CANCELLED by user: {$bookTitle}");
                    $book->chapters()->delete();
                    $book->delete();
                    if ($coverUrl) $diskPublic->delete($coverUrl);
                    if ($thumbUrl) $diskPublic->delete($thumbUrl);
                    Cache::forget($this->progressKey);
                    Cache::forget($this->cancelKey);
                    return;
                }

                $progress = round((($order - 1) / $totalFiles) * 100);
                
                // 🔥 Лог прогресу в консоль
                Log::channel('stderr')->info("JOB [{$this->progressKey}]: Прогрес {$progress}%. Файл: " . basename($file));
                
                Cache::put($this->progressKey, $progress, 3600);

                $localTemp = storage_path("app/temp_import/{$book->id}_{$order}.mp3");
                @mkdir(dirname($localTemp), 0777, true);
                
                // Завантаження файлу
                file_put_contents($localTemp, $diskPrivate->get($file));

                $info = $getID3->analyze($localTemp);
                $duration = (int) round($info['playtime_seconds'] ?? 0);
                $totalSeconds += $duration;

                $hlsFolder = storage_path("app/temp_hls/{$book->id}/{$order}");
                @mkdir($hlsFolder, 0777, true);

                // Конвертація
                $cmd = "ffmpeg -i ".escapeshellarg($localTemp)." -c:a libmp3lame -b:a 128k -f hls -hls_time 10 -hls_list_size 0 -hls_segment_filename ".escapeshellarg("$hlsFolder/seg_%03d.ts")." ".escapeshellarg("$hlsFolder/index.m3u8")." 2>&1";
                shell_exec($cmd);

                if (file_exists("$hlsFolder/index.m3u8")) {
                    foreach (scandir($hlsFolder) as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $diskPrivate->put("audio/hls/{$book->id}/{$order}/$f", fopen("$hlsFolder/$f", 'r+'));
                    }
                    AChapter::create([
                        'a_book_id' => $book->id,
                        'title' => pathinfo(basename($file), PATHINFO_FILENAME),
                        'order' => $order,
                        'audio_path' => "audio/hls/{$book->id}/{$order}/index.m3u8",
                        'duration' => $duration
                    ]);
                } else {
                     Log::channel('stderr')->error("JOB ERROR: ffmpeg не створив файли для " . basename($file));
                }

                @unlink($localTemp);
                array_map('unlink', glob("$hlsFolder/*.*"));
                @rmdir($hlsFolder);
                $order++;
            }

            $book->update(['duration' => (int) round($totalSeconds / 60)]);
            Cache::put($this->progressKey, 100, 3600);
            Log::channel('stderr')->info("✅ JOB DONE: Імпорт завершено успішно!");

        } catch (\Throwable $e) {
            // 🔥 ЦЕ НАЙВАЖЛИВІШЕ: Вивід помилки в логи
            Log::channel('stderr')->error("🔥 JOB CRASHED: " . $e->getMessage());
            Log::channel('stderr')->error($e->getTraceAsString());
            
            // Записуємо помилку в кеш, щоб контролер побачив
            Cache::put($this->progressKey, -1, 300);
            throw $e;
        }
    }
}