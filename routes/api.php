<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\ABookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\AuthorController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteApiController;
use App\Http\Controllers\Api\UserApiController;       // кабинет/профиль
use App\Http\Controllers\ListenController;             // прогресс прослушивания

// Серии
use App\Http\Controllers\Api\SeriesApiController;

// Google OAuth
use App\Http\Controllers\Api\AuthGoogleController;

// Push (FCM)
use App\Http\Controllers\Api\DeviceTokenController;

// ✅ Rewarded Ads
use App\Http\Controllers\Api\RewardsController;

use App\Http\Controllers\Api\CreditsController;

// ✅ Subscriptions (Google Play)
use App\Http\Controllers\Api\SubscriptionsController;

/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
*/

// 🔥 ОНОВЛЕНИЙ ДЕБАГ: Перевірка логів + Google Auth
Route::get('/read-logs-secret-777', function () {
    $report = ["--- BOOKA DEBUG REPORT ---"];
    
    // Перевірка конфігурації Google (чи є Client ID)
    $report[] = "Google Client ID: " . (env('GOOGLE_CLIENT_ID') ? '✅ Налаштовано' : '❌ ВІДСУТНІЙ');

    // Читання логів (якщо вони записуються)
    $path = storage_path('logs/laravel.log');
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $report[] = "\n--- ОСТАННІ ПОМИЛКИ З ЛОГ-ФАЙЛУ (laravel.log) ---";
        $report[] = e(substr($content, -5000)); // Останні 5000 символів
    } else {
        $report[] = "\nℹ️ Файл laravel.log не знайдено (Laravel пише помилки в консоль Railway)";
    }

    return response("<pre>" . implode("\n", $report) . "</pre>");
});

// ===== СТАРЫЙ login (обратная совместимость) =====
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');

// ===== НОВАЯ пара логина/рефреша =====
Route::post('/auth/login',   [AuthController::class, 'loginV2'])->middleware('throttle:30,1');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:60,1');

// ===== Вход через Google (публичный) =====
Route::post('/auth/google', [AuthGoogleController::class, 'login'])->middleware('throttle:30,1');

// ===== Регистрация токена устройства (гости и залогиненные) =====
Route::post('/push/register', [DeviceTokenController::class, 'store'])->middleware('throttle:60,1');

// ===== Каталог/жанры/авторы/серии =====
Route::get('/abooks', [ABookController::class, 'apiIndex']);
Route::get('/abooks/{id}', [ABookController::class, 'apiShow'])->whereNumber('id');
Route::get('/abooks/{id}/chapters', [ABookController::class, 'apiChapters'])->whereNumber('id');

Route::get('/genres', [GenreController::class, 'apiIndex']);
Route::get('/authors', [AuthorController::class, 'apiIndex']);

Route::get('/series', [SeriesApiController::class, 'index']);
Route::get('/series/{id}/books', [SeriesApiController::class, 'books'])->whereNumber('id');

// ===== AdMob SSV callback (публичный endpoint для Google) =====
Route::match(['GET','POST'], '/admob/ssv', [RewardsController::class, 'admobSsv'])->middleware('throttle:300,1');

// ===== Профиль (публичный; контроллер сам корректно обрабатывает гостя) =====
Route::get('/profile', [UserApiController::class, 'profile'])->middleware('throttle:120,1');


// ᐊ===============================================================
//    ✅✅✅ ДОДАНО ВЕБХУК ДЛЯ GOOGLE RTDN ✅✅✅
// ᐊ===============================================================
Route::post('/webhooks/google/rtdn', [App\Http\Controllers\Api\GoogleWebhookController::class, 'handleRtdn']);
// ᐊ===============================================================


/*
|--------------------------------------------------------------------------
| Private API (auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Избранное
    Route::get('/favorites',         [FavoriteApiController::class, 'index']);
    Route::post('/favorites/{id}',   [FavoriteApiController::class, 'store'])->whereNumber('id');
    Route::delete('/favorites/{id}', [FavoriteApiController::class, 'destroy'])->whereNumber('id');

    // Прогресс
    Route::post('/listens', [ListenController::class, 'update'])->middleware('throttle:60,1');
    Route::get('/listens',  [ListenController::class, 'index']);
    
    // обратная совместимость
    Route::post('/listen/update', [ListenController::class, 'update'])->middleware('throttle:60,1');
    Route::get('/listen',          [ListenController::class, 'index']);
    Route::get('/listened-books', [ListenController::class, 'listenedBooks']);

    // Push (тест/удаление)
    Route::post('/push/test', [DeviceTokenController::class, 'test'])->middleware('throttle:30,1');
    Route::delete('/push/unregister', [DeviceTokenController::class, 'destroy'])->middleware('throttle:60,1');

    // Logout
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('throttle:30,1');

    // ✅ me (источник истины по статусу подписки и данным авторизованного пользователя)
    // ВАЖНО: Приложение из Google Play часто падает здесь, если профиль не загружен
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('throttle:120,1');

    // ✅ Rewarded Ads — ТОЛЬКО для авторизованных
    Route::post('/rewards/prepare', [RewardsController::class, 'prepare'])->middleware('throttle:60,1');
    Route::get('/rewards/status',   [RewardsController::class, 'status'])->middleware('throttle:120,1');

    // ✅ Списание секунд в бесплатном режиме — ТОЛЬКО авторизованные
    Route::post('/credits/consume', [CreditsController::class, 'consume'])->middleware('throttle:120,1');

    // ✅ Subscriptions — проверка и статус подписки (Google Play)
    Route::post('/subscriptions/play/verify', [SubscriptionsController::class, 'verifyGooglePlay'])->middleware('throttle:60,1');
    Route::get('/subscriptions/status',        [SubscriptionsController::class, 'status'])->middleware('throttle:120,1');
});