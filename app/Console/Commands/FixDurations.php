<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ABook;
use App\Models\AChapter;
use Illuminate\Support\Facades\Storage;

class FixDurations extends Command
{
    protected $signature = 'abooks:fix-durations';
    protected $description = 'Пересчет длительности всех глав через FFmpeg/getID3';

    public function handle()
    {
        $chapters = AChapter::all();
        $this->info("Найдено глав: " . $chapters->count());

        $bar = $this->output->createProgressBar($chapters->count());
        $bar->start();

        // Инициализируем getID3 как резерв
        $getID3 = new \getID3;
        $getID3->setOption(['option_tag_id3v2' => true]);

        foreach ($chapters as $chapter) {
            // Файлы лежат на диске 'private'
            if (!Storage::disk('private')->exists($chapter->audio_path)) {
                $bar->advance();
                continue;
            }

            $fullPath = Storage::disk('private')->path($chapter->audio_path);
            $realDuration = $this->getDuration($fullPath, $getID3);

            if ($realDuration > 0 && $chapter->duration !== $realDuration) {
                // $this->line(" Исправлено: {$chapter->id} ({$chapter->duration} -> {$realDuration})");
                $chapter->duration = $realDuration;
                $chapter->save();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Пересчет длительности книг...");

        // Обновляем общую длительность книг
        foreach (ABook::all() as $book) {
            $sum = $book->chapters()->sum('duration');
            $book->duration = (int) round($sum / 60);
            $book->save();
        }

        $this->info("Готово!");
    }

    private function getDuration($path, $getID3)
    {
        // 1. FFprobe
        if (function_exists('shell_exec')) {
            $escaped = escapeshellarg($path);
            $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $escaped 2>&1";
            $out = shell_exec($cmd);
            if ($out && is_numeric(trim($out))) {
                return (int) round((float) trim($out));
            }
        }

        // 2. getID3 (резерв)
        try {
            $info = $getID3->analyze($path);
            return isset($info['playtime_seconds']) ? (int) round($info['playtime_seconds']) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}