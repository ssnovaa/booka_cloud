<?php

namespace App\Services;

use App\Models\ABook;
use App\Models\AChapter;
use App\Models\Author;
use getID3;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FtpBookImporter
{
    /**
     * @param callable $notify fn(string $level, string $message): void
     *
     * @return array{imported:int, processed:int, skipped:int}
     */
    public function import(callable $notify): array
    {
        $ftpPath = storage_path('app/ftp_books');

        $this->notify($notify, 'info', 'Старт импорта книг из FTP: ' . $ftpPath);
        Log::info('Старт импорта книг из FTP (админка)', ['path' => $ftpPath]);

        if (!is_dir($ftpPath)) {
            throw new \RuntimeException("Папка {$ftpPath} не найдена. Создайте её и загрузите папки книг.");
        }

        $bookDirs = array_filter(glob($ftpPath . '/*'), 'is_dir');
        if (empty($bookDirs)) {
            throw new \RuntimeException('Папка ftp_books пуста. Скопируйте туда папки вида "Название_Автор".');
        }

        $imported = 0;
        $skipped = 0;
        $getID3 = new getID3();

        foreach ($bookDirs as $dir) {
            $folderName = basename($dir);

            if (!str_contains($folderName, '_')) {
                $this->skip($notify, 'Папка не соответствует формату "Название_Автор" — пропуск: ' . $folderName);
                $skipped++;
                continue;
            }

            [$title, $author] = explode('_', $folderName, 2);
            $title = trim(str_replace('_', ' ', $title));
            $author = trim(str_replace('_', ' ', $author));
            $slug = Str::slug($title . '-' . $author);

            $coverFile = $this->findCover($dir);
            if (!$coverFile) {
                $this->skip($notify, 'Не найден файл обложки — пропуск: ' . $folderName);
                $skipped++;
                continue;
            }

            $chapters = $this->findChapters($dir);
            if (empty($chapters)) {
                $this->skip($notify, 'Нет mp3-файлов — пропуск: ' . $folderName);
                $skipped++;
                continue;
            }

            $authorModel = Author::firstOrCreate(['name' => $author]);
            if (ABook::where('title', $title)->where('author_id', $authorModel->id)->exists()) {
                $this->skip($notify, "Дубликат уже есть в базе: {$title} ({$author})");
                $skipped++;
                continue;
            }

            $coverPath = $this->storeCover($slug, $coverFile);

            [$bookDuration, $chapterDurations] = $this->measureDurations($chapters, $getID3);

            $book = ABook::create([
                'title' => $title,
                'description' => null,
                'author_id' => $authorModel->id,
                'reader_id' => null,
                'cover_url' => $coverPath,
                'duration' => (int) round($bookDuration / 60),
            ]);

            foreach ($chapters as $index => $file) {
                $chapterNum = $index + 1;
                $chapterPath = "audio/{$book->id}_{$chapterNum}.mp3";

                Storage::disk('private')->put($chapterPath, file_get_contents($file));

                AChapter::create([
                    'a_book_id' => $book->id,
                    'title' => 'Глава ' . $chapterNum,
                    'order' => $chapterNum,
                    'audio_path' => $chapterPath,
                    'duration' => $chapterDurations[$index] ?? null,
                ]);
            }

            $this->notify(
                $notify,
                'success',
                sprintf(
                    '✅ Импортирована "%s" (%s) — %d глав, %d сек',
                    $title,
                    $author,
                    count($chapters),
                    $bookDuration
                )
            );

            Log::info('Импорт из FTP: книга добавлена', [
                'title' => $title,
                'author' => $author,
                'chapters' => count($chapters),
                'duration_sec' => $bookDuration,
            ]);

            $imported++;
        }

        $this->notify($notify, 'info', "Готово: импортировано {$imported}, пропущено {$skipped}.");
        Log::info('Импорт из FTP завершён (админка)', ['imported' => $imported, 'skipped' => $skipped]);

        return [
            'imported' => $imported,
            'processed' => count($bookDirs),
            'skipped' => $skipped,
        ];
    }

    protected function findCover(string $dir): ?string
    {
        $covers = glob($dir . '/*.{jpg,jpeg,png,webp,bmp,JPG,JPEG,PNG,WEBP,BMP}', GLOB_BRACE);
        return $covers[0] ?? null;
    }

    protected function findChapters(string $dir): array
    {
        $chapters = glob($dir . '/*.mp3');
        sort($chapters, SORT_NATURAL);

        return $chapters;
    }

    protected function storeCover(string $slug, string $file): string
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $path = 'covers/' . $slug . '.' . $ext;
        Storage::disk('public')->put($path, file_get_contents($file));

        return $path;
    }

    /**
     * @param array<int, string> $chapters
     * @return array{int, array<int, int|null>}
     */
    protected function measureDurations(array $chapters, getID3 $getID3): array
    {
        $bookDuration = 0;
        $chapterDurations = [];

        foreach ($chapters as $file) {
            $info = $getID3->analyze($file);
            $duration = isset($info['playtime_seconds']) ? (int) round($info['playtime_seconds']) : null;

            $chapterDurations[] = $duration ?? 0;
            $bookDuration += $duration ?? 0;
        }

        return [$bookDuration, $chapterDurations];
    }

    protected function notify(callable $notify, string $level, string $message): void
    {
        $notify($level, $message);
    }

    protected function skip(callable $notify, string $message): void
    {
        $this->notify($notify, 'warning', '⏭️ ' . $message);
        Log::warning('Импорт из FTP: пропуск', ['reason' => $message]);
    }
}