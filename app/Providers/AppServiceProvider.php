<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL; // ✅ Додано імпорт для роботи з посиланнями
use App\Models\Genre;
use App\Models\Author;
use App\Models\Reader;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ Примусово використовуємо HTTPS у режимі production (на Railway)
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view) {
            // Жанры — все
            $view->with('allGenres', Genre::orderBy('name')->get());

            // Авторы только те, у которых есть книги
            $authorsWithBooks = Author::whereHas('books')->orderBy('name')->get();
            $view->with('allAuthors', $authorsWithBooks);

            // Исполнители (чтецы) только те, у которых есть книги
            $readersWithBooks = Reader::whereHas('books')->orderBy('name')->get();
            $view->with('allReaders', $readersWithBooks);
        });
    }
}