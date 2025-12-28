<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FtpBookImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ABookImportController extends Controller
{
    public function import(Request $request, FtpBookImporter $importer)
    {
        $ftpPath = storage_path('app/ftp_books');

        if (!is_dir($ftpPath)) {
            return redirect()
                ->route('admin.abooks.index')
                ->with('error', "Папка {$ftpPath} не найдена. Загрузите книги через FTP и повторите попытку.");
        }

        if (empty(File::directories($ftpPath))) {
            return redirect()
                ->route('admin.abooks.index')
                ->with('error', 'Папка ftp_books пуста. Скопируйте туда папки вида "Название книги_Автор" и запустите импорт снова.');
        }

        $log = [];

        try {
            $result = $importer->import(function (string $level, string $message) use (&$log) {
                $log[] = ['level' => $level, 'message' => $message];
            });

            return view('abooks.import_progress', [
                'log' => $log,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Импорт через админку завершился с ошибкой', ['error' => $e->getMessage()]);

            return view('abooks.import_progress', [
                'log' => $log,
                'error' => $e->getMessage(),
                'result' => null,
            ]);
        }
    }
}