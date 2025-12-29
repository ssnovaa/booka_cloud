<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Models\ABook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // Додано фасад Storage

class SeriesApiController extends Controller
{
    /**
     * GET /api/series
     * Список серій для вкладки «Серії».
     * Повертаємо коротку інфу + обкладинку першої книги серії.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);

        $series = Series::withCount('books')
            ->orderBy('title')
            ->paginate($perPage)
            ->withQueryString();

        $data = $series->getCollection()->map(function (Series $s) {
            // Перша книга серії (по id) — щоб дістати обкладинку
            $first = $s->books()
                ->orderBy('id')
                ->select(['id','title','cover_url','thumb_url'])
                ->first();

            // 🔥 ВИПРАВЛЕННЯ: отримуємо чистий шлях і генеруємо посилання на R2
            $rawPath = $first?->getRawOriginal('thumb_url') ?? $first?->getRawOriginal('cover_url');
            
            $firstCoverAbs = null;
            if ($rawPath) {
                $firstCoverAbs = str_starts_with($rawPath, 'http') 
                    ? $rawPath 
                    : Storage::disk('s3')->url($rawPath);
            }

            return [
                'id'            => (int) $s->id,
                'title'         => $s->title,
                'description'   => $s->description,
                'books_count'   => (int) $s->books_count,
                'first_cover'   => $firstCoverAbs, 
            ];
        });

        return response()->json([
            'current_page' => $series->currentPage(),
            'last_page'    => $series->lastPage(),
            'per_page'     => $series->perPage(),
            'total'        => $series->total(),
            'data'         => $data->values(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/series/{id}/books
     * Книги в серії — формат, сумісний із Book.fromJson у додатку.
     */
    public function books($id, Request $request)
    {
        $s = Series::findOrFail($id);

        $query = ABook::with(['author','reader','genres'])
            ->where('series_id', $s->id)
            ->orderBy('id');

        $books = $query->get()->map(function (ABook $book) use ($s) {
            
            // 🔥 ВИПРАВЛЕННЯ: генеруємо посилання на R2 для книг серії
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

            return [
                'id'          => (int) $book->id,
                'title'       => $book->title,
                'author'      => $book->author?->name,
                'reader'      => $book->reader?->name,
                'description' => $book->description,
                'duration'    => (string) $book->duration,
                'cover_url'   => $coverAbs,
                'thumb_url'   => $thumbAbs,
                'genres'      => $book->genres->pluck('name')->values(),
                'series'      => $s->title,
            ];
        });

        return response()->json($books->values(), 200, [], JSON_UNESCAPED_UNICODE);
    }
}