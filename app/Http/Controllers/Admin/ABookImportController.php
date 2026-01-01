<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FtpBookImporter;
use App\Models\Genre; // ✅ Додано для методу bulkUploadView
use App\Models\Reader; // ✅ Додано для методу bulkUploadView
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ABookImportController extends Controller
{
    /**
     * Порожня сторінка, яка служить контейнером для "живого логу" імпорту.
     * (Використовується для старого методу імпорту через папку на сервері)
     */
    public function import()
    {
        return view('abooks.import_progress');
    }

    /**
     * Стрімінг прогресу імпорту.
     * Викликається через JS на сторінці import_progress.
     */
    public function runImport(FtpBookImporter $importer)
    {
        return new StreamedResponse(function() use ($importer) {
            // Вимикаємо буферизацію PHP, щоб дані надходили в браузер миттєво
            if (ob_get_level()) ob_end_clean();

            $send = function($data) {
                echo json_encode($data) . "\n";
                if (ob_get_level()) ob_flush();
                flush();
            };

            try {
                // Запускаємо імпорт із сервісу
                $importer->import(function (string $level, string $message) use ($send) {
                    $send(['type' => 'log', 'level' => $level, 'message' => $message]);
                });

                $send(['type' => 'done']);
            } catch (\Throwable $e) {
                $send(['type' => 'error', 'message' => $e->getMessage()]);
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no', // Критично для Railway/Nginx
        ]);
    }

    /**
     * НОВИЙ МЕТОД: Показ сторінки для вибору папок прямо з вашого ПК.
     * Саме цей метод дозволить вам вантажити книги без FTP.
     */
    public function bulkUploadView()
    {
        // Отримуємо списки жанрів та чтеців для загальних налаштувань сесії завантаження
        $genres = Genre::orderBy('name')->get();
        $readers = Reader::orderBy('name')->get();

        return view('admin.abooks.bulk_upload', compact('genres', 'readers'));
    }
}