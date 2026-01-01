<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FtpBookImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ABookImportController extends Controller
{
    // Просто показываем страницу
    public function import()
    {
        return view('abooks.import_progress');
    }

    // Тот самый метод, который стримит прогресс
    public function runImport(FtpBookImporter $importer)
    {
        return new StreamedResponse(function() use ($importer) {
            // Отключаем буферизацию, чтобы данные сразу улетали в браузер
            if (ob_get_level()) ob_end_clean();

            $send = function($data) {
                echo json_encode($data) . "\n";
                if (ob_get_level()) ob_flush();
                flush();
            };

            try {
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
            'X-Accel-Buffering' => 'no', // Важно для Nginx (Railway)
        ]);
    }
}