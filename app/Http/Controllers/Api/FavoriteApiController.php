<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ABook;
use Illuminate\Support\Facades\Storage; // Добавлено для работы с R2

class FavoriteApiController extends Controller
{
    /**
     * Список избранных книг.
     * Исправлено: теперь возвращает полные ссылки на Cloudflare R2.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Подгружаем связи, чтобы минимизировать запросы (Eager Loading)
        $books = $user->favoriteBooks()
            ->with(['author', 'reader', 'genres', 'series'])
            ->get();

        $data = $books->map(function ($book) {
            // 🔥 Отримуємо оригінальні шляхи з БД без втручання аксесорів
            $rawCover = $book->getRawOriginal('cover_url');
            $rawThumb = $book->getRawOriginal('thumb_url');

            $coverAbs = null;
            if ($rawCover) {
                $coverAbs = str_starts_with($rawCover, 'http') 
                    ? $rawCover 
                    : Storage::disk('s3')->url($rawCover);
            }

            $thumbAbs = null;
            if ($rawThumb) {
                $thumbAbs = str_starts_with($rawThumb, 'http') 
                    ? $rawThumb 
                    : Storage::disk('s3')->url($rawThumb);
            }

            // Возвращаем структуру, которую ждет Flutter (Book.fromJson)
            return [
                'id'          => (int) $book->id,
                'title'       => $book->title,
                'author'      => $book->author?->name,
                'reader'      => $book->reader?->name,
                'description' => $book->description,
                'duration'    => (string) $book->duration,
                'cover_url'   => $coverAbs,
                'thumb_url'   => $thumbAbs,
                'series'      => $book->series?->title,
                'series_id'   => $book->series_id,
                'genres'      => $book->genres->pluck('name')->values(),
            ];
        });

        return response()->json([
            'favorites' => $data,
        ]);
    }

    // Добавить книгу в избранное
    public function store(Request $request, $id)
    {
        $user = $request->user();
        $book = ABook::findOrFail($id);

        if (!$user->favoriteBooks()->where('a_book_id', $book->id)->exists()) {
            $user->favoriteBooks()->attach($book->id);
        }

        return response()->json(['message' => 'Книга добавлена в избранное']);
    }

    // Удалить книгу из избранного
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $book = ABook::findOrFail($id);

        $user->favoriteBooks()->detach($book->id);

        return response()->json(['message' => 'Книга удалена из избранного']);
    }
}