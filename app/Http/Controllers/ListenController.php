<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Listen;
use App\Models\ABook;
use App\Models\AChapter;
use App\Models\ListenLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage; // Добавлено для работы с R2

class ListenController extends Controller
{
    /**
     * Получить последнюю позицию прослушивания пользователя.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Неавторизовано'], 401);
        }

        $aBookId    = (int) $request->query('a_book_id', 0);
        $aChapterId = (int) $request->query('a_chapter_id', 0);

        if ($aBookId && $aChapterId) {
            $listen = Listen::where('user_id', $user->id)
                ->where('a_book_id', $aBookId)
                ->where('a_chapter_id', $aChapterId)
                ->first();

            return response()->json([
                'a_book_id'    => $aBookId,
                'a_chapter_id' => $aChapterId,
                'position'     => (int) ($listen?->position ?? 0),
            ]);
        }

        $listen = Listen::where('user_id', $user->id)
            ->latest('updated_at')
            ->first();

        if (!$listen) {
            return response()->json(null);
        }

        return response()->json([
            'a_book_id'    => (int) $listen->a_book_id,
            'a_chapter_id' => (int) $listen->a_chapter_id,
            'position'     => (int) $listen->position,
        ]);
    }

    /**
     * Получить позицию прослушивания для конкретной главы.
     */
    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'a_book_id'    => ['required','integer','exists:a_books,id'],
            'a_chapter_id' => ['required','integer','exists:a_chapters,id'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Неавторизовано'], 401);
        }

        $aBookId    = (int) $request->query('a_book_id');
        $aChapterId = (int) $request->query('a_chapter_id');

        $listen = Listen::where('user_id', $user->id)
            ->where('a_book_id', $aBookId)
            ->where('a_chapter_id', $aChapterId)
            ->first();

        return response()->json([
            'a_book_id'    => $aBookId,
            'a_chapter_id' => $aChapterId,
            'position'     => (int) ($listen?->position ?? 0),
        ]);
    }

    /**
     * Обновить прогресс прослушивания и записать логи времени.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Неавторизовано'], 401);
        }

        $data = $request->validate([
            'a_book_id'    => ['required','integer','exists:a_books,id'],
            'a_chapter_id' => ['required','integer','exists:a_chapters,id'],
            'position'     => ['required','integer','min:0'],
            'played'       => ['nullable','integer','min:0'],
        ]);

        $chapter = AChapter::select('id','a_book_id','duration')->find($data['a_chapter_id']);
        if (!$chapter || (int) $chapter->a_book_id !== (int) $data['a_book_id']) {
            return response()->json(['message' => 'Глава не принадлежит этой книге'], 422);
        }

        $position = (int) $data['position'];
        $duration = is_null($chapter->duration) ? null : max(0, (int) $chapter->duration);
        if ($duration !== null) {
            $position = max(0, min($position, $duration));
        }

        $now = now();

        $listen = Listen::where([
            'user_id'      => $user->id,
            'a_book_id'    => (int) $data['a_book_id'],
            'a_chapter_id' => (int) $data['a_chapter_id'],
        ])->first();

        $prevPos = $listen?->position ?? 0;
        $prevAt  = $listen?->updated_at;

        $credited = 0;
        if (array_key_exists('played', $data) && $data['played'] !== null) {
            $played = max(0, (int) $data['played']);
            $cap = $prevAt ? $prevAt->diffInSeconds($now) + 10 : 3600;
            $credited = min($played, max(0, $cap));
        } else {
            $deltaPos = $position - $prevPos;
            if ($deltaPos > 0) {
                $cap = $prevAt ? $prevAt->diffInSeconds($now) + 10 : 3600;
                $credited = min($deltaPos, max(0, $cap));
            }
        }

        if ($listen) {
            $listen->position   = $position;
            $listen->updated_at = $now;
            $listen->save();
        } else {
            $listen = Listen::create([
                'user_id'      => $user->id,
                'a_book_id'    => (int) $data['a_book_id'],
                'a_chapter_id' => (int) $data['a_chapter_id'],
                'position'     => $position,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        if ($credited > 0) {
            ListenLog::create([
                'user_id'      => $user->id,
                'a_book_id'    => (int) $data['a_book_id'],
                'a_chapter_id' => (int) $data['a_chapter_id'],
                'seconds'      => $credited,
                'created_at'   => $now,
            ]);
        }

        $isPaidValid = false;
        if ($user->paid_until) {
            $date = $user->paid_until instanceof Carbon 
                ? $user->paid_until 
                : Carbon::parse($user->paid_until);
            $isPaidValid = $date->isFuture();
        }

        return response()->json([
            'status'       => 'ok',
            'a_book_id'    => (int) $listen->a_book_id,
            'a_chapter_id' => (int) $listen->a_chapter_id,
            'position'     => (int) $listen->position,
            'updated_at'   => $listen->updated_at,
            'credited'     => $credited,
            'user_is_paid' => $isPaidValid,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->update($request);
    }

    /**
     * GET /api/listened-books
     * Список прослушанных книг (позиция > 0).
     */
    public function listenedBooks(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Неавторизовано'], 401);
        }

        $listenedBookIds = Listen::where('user_id', $user->id)
            ->where('position', '>', 0)
            ->distinct()
            ->pluck('a_book_id');

        $books = ABook::with('author')
            ->whereIn('id', $listenedBookIds)
            ->get()
            ->map(function ($book) {
                // 🔥 Исправлено: получаем чистую ссылку из БД и формируем S3 URL
                $rawCover = $book->getRawOriginal('cover_url');
                
                $coverAbs = null;
                if ($rawCover) {
                    $coverAbs = str_starts_with($rawCover, 'http') 
                        ? $rawCover 
                        : Storage::disk('s3')->url($rawCover);
                }

                return [
                    'id'        => (int) $book->id,
                    'title'     => (string) $book->title,
                    'author'    => $book->author?->name ?? 'Невідомий',
                    'cover_url' => $coverAbs, // Прямая ссылка на R2
                ];
            })
            ->values();

        return response()->json($books);
    }
}