<?php

namespace App\Http\Controllers;

use App\Models\ABook;
use App\Models\AChapter;
use App\Models\Genre;
use App\Models\Author;
use App\Models\Reader;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ABookController extends Controller
{
    // ======================= [АДМІНІСТРУВАННЯ: WEB] =======================

    // Список книг
    public function index(Request $request)
    {
        $query = ABook::with(['author', 'reader', 'agency']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('author', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  })
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($genreId = $request->input('genre')) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        if ($authorId = $request->input('author')) {
            $query->where('author_id', $authorId);
        }

        if ($readerId = $request->input('reader')) {
            $query->where('reader_id', $readerId);
        }

        if ($sort = $request->input('sort')) {
            if ($sort === 'new') {
                $query->orderBy('created_at', 'desc');
            } elseif ($sort === 'title') {
                $query->orderBy('title');
            } elseif ($sort === 'duration') {
                $query->orderBy('duration', 'desc');
            }
        }

        $books = $query->paginate(12)->withQueryString();

        $allGenres = Genre::orderBy('name')->get();
        $allAuthors = Author::whereHas('books')->orderBy('name')->get();
        $allReaders = Reader::whereHas('books')->orderBy('name')->get();

        return view('abooks.index', compact('books', 'allGenres', 'allAuthors', 'allReaders'));
    }

    // Форма створення
    public function create()
    {
        $genres = Genre::orderBy('name')->get();
        $readers = Reader::orderBy('name')->get();
        $agencies = Agency::orderBy('name')->get();

        return view('admin.abooks.create', compact('genres', 'readers', 'agencies'));
    }

    // Збереження (Завантаження безпосередньо в Cloudflare R2)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'reader_id' => 'nullable|exists:readers,id',
            'series_id' => 'nullable|exists:series,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'description' => 'nullable|string',
            'genres' => 'required|array',
            'genres.*' => 'integer|exists:genres,id',
            'duration' => 'nullable|integer',
            'cover_file' => 'required|image|mimes:jpg,jpeg,png',
            'audio_files' => 'required|array',
            'audio_files.*' => 'required|mimes:mp3,wav',
        ]);

        // 1. ЗАВАНТАЖЕННЯ ОБКЛАДИНКИ НА R2
        $coverFile = $request->file('cover_file');
        $coverName = 'covers/' . time() . '_' . $coverFile->getClientOriginalName();
        Storage::disk('s3')->put($coverName, fopen($coverFile->getRealPath(), 'r+'), 'public');

        // 2. ГЕНЕРАЦІЯ ТА ЗАВАНТАЖЕННЯ МІНІАТЮРИ НА R2
        $image = Image::read($coverFile->getRealPath())->cover(200, 300);
        $thumbName = 'covers/thumb_' . basename($coverName);
        Storage::disk('s3')->put($thumbName, (string) $image->toJpeg(80), 'public');

        $author = Author::firstOrCreate(['name' => $validated['author']]);

        $book = ABook::create([
            'title' => $validated['title'],
            'author_id' => $author->id,
            'reader_id' => $validated['reader_id'] ?? null,
            'series_id' => $validated['series_id'] ?? null,
            'agency_id' => $validated['agency_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'cover_url' => $coverName, // Зберігаємо шлях у R2
            'thumb_url' => $thumbName, 
        ]);

        $book->genres()->sync($validated['genres']);

        // 3. ЗАВАНТАЖЕННЯ АУДІОФАЙЛІВ НА R2
        foreach ($request->file('audio_files') as $index => $audioFile) {
            $audioName = 'audio/' . time() . '_' . $audioFile->getClientOriginalName();
            Storage::disk('s3')->put($audioName, fopen($audioFile->getRealPath(), 'r+'), 'public');

            AChapter::create([
                'a_book_id' => $book->id,
                'title' => 'Глава ' . ($index + 1),
                'order' => $index + 1,
                'audio_path' => $audioName,
            ]);
        }

        return redirect('/abooks')->with('success', 'Книгу успішно додано в хмару R2!');
    }

    // Форма редагування
    public function edit($id)
    {
        $book = ABook::with(['genres', 'author', 'reader', 'agency'])->findOrFail($id);
        
        $genres = Genre::orderBy('name')->get();
        $readers = Reader::orderBy('name')->get();
        $agencies = Agency::orderBy('name')->get();

        return view('admin.abooks.edit', compact('book', 'genres', 'readers', 'agencies'));
    }

    // Оновлення
    public function update(Request $request, $id)
    {
        $book = ABook::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'reader_id' => 'nullable|exists:readers,id',
            'series_id' => 'nullable|exists:series,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'description' => 'nullable|string',
            'genres' => 'required|array',
            'genres.*' => 'integer|exists:genres,id',
            'duration' => 'nullable|integer',
            'cover_file' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('cover_file')) {
            // Видаляємо старі файли з R2
            if ($book->cover_url) {
                Storage::disk('s3')->delete($book->cover_url);
            }
            if ($book->thumb_url) {
                Storage::disk('s3')->delete($book->thumb_url);
            }

            $newCoverFile = $request->file('cover_file');
            $newCoverName = 'covers/' . time() . '_' . $newCoverFile->getClientOriginalName();
            Storage::disk('s3')->put($newCoverName, fopen($newCoverFile->getRealPath(), 'r+'), 'public');

            $image = Image::read($newCoverFile->getRealPath())->cover(200, 300);
            $thumbName = 'covers/thumb_' . basename($newCoverName);
            Storage::disk('s3')->put($thumbName, (string) $image->toJpeg(80), 'public');

            $book->cover_url = $newCoverName;
            $book->thumb_url = $thumbName;
        }

        $author = Author::firstOrCreate(['name' => $validated['author']]);
        
        $book->author_id = $author->id;
        $book->reader_id = $validated['reader_id'] ?? null;
        $book->series_id = $validated['series_id'] ?? null;
        $book->agency_id = $validated['agency_id'] ?? null;
        $book->title = $validated['title'];
        $book->description = $validated['description'] ?? null;
        $book->duration = $validated['duration'] ?? null;
        $book->save();

        $book->genres()->sync($validated['genres']);

        return redirect()->route('admin.abooks.index')->with('success', 'Книгу оновлено в R2');
    }

    // Видалення
    public function destroy($id)
    {
        $book = ABook::findOrFail($id);

        // Видаляємо обкладинки з R2
        if ($book->cover_url) {
            Storage::disk('s3')->delete($book->cover_url);
        }
        if ($book->thumb_url) {
            Storage::disk('s3')->delete($book->thumb_url);
        }

        // Видаляємо всі аудіофайли глав з R2
        $book->chapters()->each(function ($chapter) {
            if ($chapter->audio_path) {
                Storage::disk('s3')->delete($chapter->audio_path);
            }
            $chapter->delete();
        });

        $book->genres()->detach();
        $book->delete();

        return redirect('/admin/abooks')->with('success', 'Книгу та файли видалено з R2');
    }

    // Перегляд книги (адмінка)
    public function show($id)
    {
        $book = ABook::with('chapters')->findOrFail($id);
        return view('abooks.show', compact('book'));
    }

    // ======================= [API: MOBILE APP] =======================
    
    // Каталог (JSON)
    public function apiIndex(Request $request)
    {
        $query = ABook::with(['author', 'reader', 'genres', 'series', 'agency']);

        // Пошук
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Фільтри
        if ($genre = $request->input('genre')) {
            $genres = is_array($genre) ? $genre : explode(',', $genre);
            $genres = array_filter(array_map('trim', $genres), fn($v) => $v !== '');
            if (!empty($genres)) {
                $query->whereHas('genres', function ($q) use ($genres) {
                    $q->where(function ($w) use ($genres) {
                        foreach ($genres as $g) {
                            if (is_numeric($g)) {
                                $w->orWhere('genres.id', $g);
                            } else {
                                $w->orWhere('genres.name', 'like', "%{$g}%");
                            }
                        }
                    });
                });
            }
        }

        if ($author = $request->input('author')) {
            $query->whereHas('author', function ($q) use ($author) {
                if (is_numeric($author)) {
                    $q->where('id', $author);
                } else {
                    $q->where('name', 'like', "%{$author}%");
                }
            });
        }

        if ($reader = $request->input('reader')) {
            $query->whereHas('reader', function ($q) use ($reader) {
                if (is_numeric($reader)) {
                    $q->where('id', $reader);
                } else {
                    $q->where('name', 'like', "%{$reader}%");
                }
            });
        }

        if ($seriesId = $request->input('series_id')) {
            $ids = is_array($seriesId) ? $seriesId : explode(',', $seriesId);
            $ids = array_filter(array_map('trim', $ids), fn($v) => $v !== '');
            if (!empty($ids)) {
                $query->whereIn('series_id', $ids);
            }
        }
        if ($series = $request->input('series')) {
            $names = is_array($series) ? $series : explode(',', $series);
            $names = array_filter(array_map('trim', $names), fn($v) => $v !== '');
            if (!empty($names)) {
                $query->whereHas('series', function ($q) use ($names) {
                    $q->where(function ($w) use ($names) {
                        foreach ($names as $n) {
                            if (is_numeric($n)) {
                                $w->orWhere('id', $n);
                            } else {
                                $clean = trim($n, " \t\n\r\0\x0B\"'«»„“”");
                                $w->orWhere('title', 'like', "%{$clean}%");
                            }
                        }
                    });
                });
            }
        }

        // Сортування
        if ($sort = $request->input('sort')) {
            if ($sort === 'new') {
                $query->orderBy('created_at', 'desc');
            } elseif ($sort === 'title') {
                $query->orderBy('title');
            } elseif ($sort === 'duration') {
                $query->orderBy('duration', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = intval($request->input('per_page', 20));
        $books = $query->paginate($perPage)->withQueryString();

        $result = [
            'current_page' => $books->currentPage(),
            'last_page'    => $books->lastPage(),
            'per_page'     => $books->perPage(),
            'total'        => $books->total(),
            'data'         => $books->map(function ($book) {
                return [
                    'id'          => $book->id,
                    'title'       => $book->title,
                    'author'      => $book->author?->name,
                    'reader'      => $book->reader?->name,
                    'description' => $book->description,
                    'duration'    => $book->duration,
                    
                    // 🔥 ХМАРНІ ПОСИЛАННЯ НА ОБКЛАДИНКИ (S3)
                    'cover_url'   => $book->cover_url ? Storage::disk('s3')->url($book->cover_url) : null,
                    'thumb_url'   => $book->thumb_url ? Storage::disk('s3')->url($book->thumb_url) : null,
                    
                    'genres'      => $book->genres->map(function ($genre) {
                        return [
                            'id'   => $genre->id,
                            'name' => $genre->name,
                        ];
                    })->values(),
                    'series'      => $book->series?->title,
                    'series_id'   => $book->series_id,
                ];
            }),
        ];

        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Одна книга (JSON)
    public function apiShow($id)
    {
        $book = ABook::with(['author', 'reader', 'genres', 'series', 'agency'])->findOrFail($id);

        $result = [
            'id'          => $book->id,
            'title'       => $book->title,
            'author'      => $book->author?->name,
            'reader'      => $book->reader?->name,
            'description' => $book->description,
            'duration'    => $book->duration,
            
            // 🔥 ХМАРНІ ПОСИЛАННЯ НА ОБКЛАДИНКИ (S3)
            'cover_url'   => $book->cover_url ? Storage::disk('s3')->url($book->cover_url) : null,
            'thumb_url'   => $book->thumb_url ? Storage::disk('s3')->url($book->thumb_url) : null,
            
            'genres'      => $book->genres->map(function ($genre) {
                return [
                    'id'   => $genre->id,
                    'name' => $genre->name,
                ];
            })->values(),
            'series'      => $book->series?->title,
            'series_id'   => $book->series_id,
        ];

        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    // Глави книги (JSON)
    public function apiChapters($id)
    {
        $book = ABook::findOrFail($id);

        $chapters = AChapter::where('a_book_id', $book->id)
            ->orderBy('order')
            ->get()
            ->map(function ($chapter) {
                return [
                    'id'        => $chapter->id,
                    'duration'  => $chapter->duration,
                    'title'     => $chapter->title,
                    'order'     => $chapter->order,
                    'audio_url' => $chapter->audio_path ? url('/audio/' . $chapter->id) : null,
                ];
            })->values();

        return response()->json($chapters, 200, [], JSON_UNESCAPED_UNICODE);
    }
}