<?php

namespace App\Console\Commands;

use App\Services\FtpBookImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportFtpBooks extends Command
{
    protected $signature = 'abooks:import-ftp';
    protected $description = 'Импорт аудиокниг из storage/app/ftp_books (папка: Название книги_Автор, mp3 + любая картинка) с автоопределением длительности';

    public function __construct(protected FtpBookImporter $importer)
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $result = $this->importer->import(function (string $level, string $message) {
                if ($level === 'warning') {
                    $this->warn($message);
                    return;
                }

                if ($level === 'error') {
                    $this->error($message);
                    return;
                }

                $this->info($message);
            });

            $this->info('Готово! Импортировано книг: ' . $result['imported']);
            return 0;
        } catch (\Throwable $e) {
            Log::error('Импорт из FTP: аварийное завершение', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Импорт прерван: ' . $e->getMessage());
            return 1;
        }
    }
}