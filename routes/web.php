<?php
// routes/web.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\ABookController;
use App\Http\Controllers\AudioStreamController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ListenController;
use App\Http\Controllers\GenreController;

// Адмінські контролери
use App\Http\Controllers\Admin\ReaderController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\ABookImportController;
use App\Http\Controllers\Admin\SeriesController;
use App\Http\Controllers\Admin\PushAdminController;
use App\Http\Controllers\Admin\ListeningStatsAdminController;
use App\Http\Controllers\Admin\RoyaltyAdminController;
use App\Http\Controllers\Admin\AuthorController as AdminAuthorController; // Аліас для адмінського контролера авторів
use App\Http\Controllers\Admin\AgencyController; // 🏢 Контролер агентств

use App\Http\Controllers\SeriesPublicController;
use App\Http\Controllers\ProfileDashboardController;

use App\Http\Middleware\IsAdmin;
use App\Models\ABook;

/*
|--------------------------------------------------------------------------
| Домашня сторінка — показує свіжі книги та жанри
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    $books  = ABook::latest()->take(16)->get();
    $genres = \App\Models\Genre::withCount('books')->orderBy('name')->get();

    return view('welcome', [
        'books' => $books,
        'genres' => $genres,
        'user' => Auth::user(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Публічний каталог аудіокниг
|--------------------------------------------------------------------------
*/
Route::get('/abooks', [ABookController::class, 'index'])->name('abooks.index');
Route::get('/abooks/{id}', [ABookController::class, 'show'])->whereNumber('id')->name('abooks.show');

/*
|--------------------------------------------------------------------------
| Жанри — сторінка списку жанрів
|--------------------------------------------------------------------------
*/
Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');

/*
|--------------------------------------------------------------------------
| Публічна сторінка серії — усі книги серії
|--------------------------------------------------------------------------
*/
Route::get('/series/{id}', [SeriesPublicController::class, 'show'])
    ->whereNumber('id')
    ->name('series.show');

/*
|--------------------------------------------------------------------------
| Кабінет користувача (візуальна панель як у застосунку)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Головна сторінка кабінету
    Route::get('/cabinet', [ProfileDashboardController::class, 'index'])
        ->name('cabinet.index');

    // Обрані книги (зі сторінкуванням)
    Route::get('/cabinet/favorites', [ProfileDashboardController::class, 'favorites'])
        ->name('cabinet.favorites');

    // Прослухані книги (історія, сторінкування)
    Route::get('/cabinet/listened', [ProfileDashboardController::class, 'listened'])
        ->name('cabinet.listened');
});

/*
|--------------------------------------------------------------------------
| Адмінська частина (лише для авторизованих адміністраторів)
|--------------------------------------------------------------------------
| Використовується власний проміжний шар IsAdmin.
*/
Route::middleware(['auth', IsAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Панель адміністратора
        Route::get('/', fn () => view('admin.dashboard'))->name('dashboard');

        // Керування книгами
        Route::get('/abooks', [ABookController::class, 'index'])->name('abooks.index');
        Route::get('/abooks/create', [ABookController::class, 'create'])->name('abooks.create');
        Route::post('/abooks', [ABookController::class, 'store'])->name('abooks.store');
        Route::get('/abooks/{id}/edit', [ABookController::class, 'edit'])->whereNumber('id')->name('abooks.edit');
        Route::put('/abooks/{id}', [ABookController::class, 'update'])->whereNumber('id')->name('abooks.update');
        Route::delete('/abooks/{id}', [ABookController::class, 'destroy'])->whereNumber('id')->name('abooks.destroy');

        // Імпорт книг з FTP
        Route::post('/abooks/import', [ABookImportController::class, 'import'])->name('abooks.import');
        Route::get('/abooks/import/run', [ABookImportController::class, 'runImport'])->name('abooks.import.run');
        
        // 🔥 Маршрут для перевірки прогресу (для JS)
        Route::get('/abooks/import/progress', [ABookImportController::class, 'checkProgress'])->name('abooks.import.progress');
		
		// Всередині групи admin. (після abooks.import.progress)
		Route::post('/abooks/import/cancel', [ABookImportController::class, 'cancelImport'])->name('abooks.import.cancel');
        
        // Масове завантаження (Drag and Drop)
        Route::get('/abooks/bulk-upload', [ABookImportController::class, 'bulkUploadView'])->name('abooks.bulk-upload');

        // Керування жанрами
        Route::resource('genres', GenreController::class)->except(['show']);

        // Серії
        Route::resource('series', SeriesController::class)->except(['show']);

        // Керування читцями
        Route::resource('readers', ReaderController::class);

        // 🏢 Керування агентствами (Правовласниками)
        Route::resource('agencies', AgencyController::class);
        
        // 🏢 Експорт роялті
        Route::post('/royalties/export', [RoyaltyAdminController::class, 'export'])->name('royalties.export');

        // 👨‍💼 Керування авторами
        Route::resource('authors', AdminAuthorController::class)->only(['index', 'edit', 'update']);

        // Керування главами аудіокниг
        Route::prefix('abooks/{book}/chapters')->name('chapters.')->group(function () {
            Route::get('/create', [ChapterController::class, 'create'])->name('create');
            Route::post('/', [ChapterController::class, 'store'])->name('store');
            Route::get('/{chapter}/edit', [ChapterController::class, 'edit'])->name('edit');
            Route::put('/{chapter}', [ChapterController::class, 'update'])->name('update');
            Route::delete('/{chapter}', [ChapterController::class, 'destroy'])->name('destroy');
        });

        // PUSH сповіщення
        Route::prefix('push')->name('push.')->group(function () {
            Route::get('/',  [PushAdminController::class, 'create'])->name('create');
            Route::post('/', [PushAdminController::class, 'store'])->name('store');
        });

        // 📊 Статистика прослуховувань
        Route::get('/listens/stats', [ListeningStatsAdminController::class, 'index'])
            ->name('listens.stats');

        Route::get('/listens/stats/export.csv', [ListeningStatsAdminController::class, 'exportCsv'])
            ->name('listens.stats.export');

        Route::get('/listens/stats/export.books.csv', [ListeningStatsAdminController::class, 'exportBooksCsv'])
            ->name('listens.stats.export.books');

        Route::get('/listens/books/{a_book_id}', [ListeningStatsAdminController::class, 'book'])
            ->whereNumber('a_book_id')
            ->name('listens.book');

        Route::get('/listens/books/{a_book_id}/export.series.csv', [ListeningStatsAdminController::class, 'bookExportSeriesCsv'])
            ->whereNumber('a_book_id')
            ->name('listens.book.export.series');

        Route::get('/listens/books/{a_book_id}/export.chapters.csv', [ListeningStatsAdminController::class, 'bookExportChaptersCsv'])
            ->whereNumber('a_book_id')
            ->name('listens.book.export.chapters');

        // 👤 Звіт по авторам
        Route::get('/listens/authors', [ListeningStatsAdminController::class, 'authors'])
            ->name('listens.authors');

        Route::get('/listens/authors/export.csv', [ListeningStatsAdminController::class, 'exportAuthorsCsv'])
            ->name('listens.authors.export');

        // 💰 Роялті
        Route::get('/royalties', [RoyaltyAdminController::class, 'index'])
            ->name('royalties.index');
    });

/*
|--------------------------------------------------------------------------
| Потокове аудіо (HLS: Плейлист + Сегменти)
|--------------------------------------------------------------------------
| 🔥 ВИПРАВЛЕНО: Route::any для підтримки OPTIONS та where для .ts файлів
*/
Route::any('/audio/{id}/{file?}', [AudioStreamController::class, 'stream'])
    ->whereNumber('id')
    ->where('file', '.*') // Дозволяємо крапки (для .ts і .m3u8)
    ->name('audio.stream');

/*
|--------------------------------------------------------------------------
| Авторизація та реєстрація (Laravel Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Обрані книги (лише для авторизованих користувачів)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/abooks/{id}/favorite', [FavoriteController::class, 'toggle'])
        ->whereNumber('id')
        ->name('favorites.toggle');

    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');
});

/*
|--------------------------------------------------------------------------
| Прогрес прослуховування (лише для авторизованих користувачів)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/listen/update', [ListenController::class, 'update'])->name('listen.update');
    Route::get('/listen', [ListenController::class, 'get'])->name('listen.get');
});

/*
|--------------------------------------------------------------------------
| Тестовий API-маршрут  11
|--------------------------------------------------------------------------
*/
Route::get('/api/debug-web', function () {
    return response()->json(['from' => 'web.php']);
});

// ... весь ваш старий код вище ...

/*
|--------------------------------------------------------------------------
| 🔥 ЛОГИ (Секретний доступ)
|--------------------------------------------------------------------------
*/
Route::get('/api/read-logs-secret-777', function () {
    $logFile = storage_path('logs/laravel.log');

    if (!file_exists($logFile)) {
        return "Файл логов не найден.";
    }

    // Читаємо останні 5 МБ логів (це багато, ви побачите все)
    // Якщо хочете ВЕСЬ файл, замініть цю логіку на file_get_contents($logFile)
    $maxBytes = 5 * 1024 * 1024; // 5 MB
    $fileSize = filesize($logFile);
    
    $handle = fopen($logFile, 'r');
    if ($fileSize > $maxBytes) {
        fseek($handle, -$maxBytes, SEEK_END);
        // Пропускаємо частковий рядок на початку, щоб було красиво
        fgets($handle); 
    }
    
    $content = '';
    while (!feof($handle)) {
        $content .= fread($handle, 8192);
    }
    fclose($handle);

    return response('<pre style="font-family: monospace; white-space: pre-wrap; background: #222; color: #0f0; padding: 15px; font-size: 13px;">' . htmlspecialchars($content) . '</pre>');
});