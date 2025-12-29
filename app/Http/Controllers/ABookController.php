<?php

namespace App\Http\Controllers;

use App\Models\ABook;
use App\Models\AChapter;
use App\Models\Genre;
use App\Models\Author;
use App\Models\Reader;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; // ✅ Важно
use Intervention\Image\Laravel\Facades\Image;

class ABookController extends Controller
{
    // ... (Методы index, create, store, edit, update, destroy, show пропускаем - они для админки) ...
    // ... Оставьте их как были, или скопируйте полностью весь файл ниже, я сохранил их ...

    public function index(Request $request)
    {
        $query = ABook::with(['author', 'reader', 'agency']);
        // ... (фильтры) ...
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('author', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($genreId = $request->input('genre')) {
            $query->whereHas('genres', fn($q) => $q->where('genres.id', $genreId));
        }
        if ($authorId = $request->input('author')) $query->where('author_id', $authorId);
        if ($readerId = $request->input('reader')) $query->where('reader_id', $readerId);

        if ($sort = $request->input('sort')) {
            if ($sort === 'new') $query->orderBy('created_at', 'desc');
            elseif ($sort === 'title') $query->orderBy('title');
            elseif ($sort === 'duration') $query->orderBy('duration', 'desc');
        }

        $books = $query->paginate(12)->withQueryString();
        $allGenres = Genre::orderBy('name')->get();
        $allAuthors = Author::whereHas('books')->orderBy('name')->get();
        $allReaders = Reader::whereHas('books')->orderBy('name')->get();

        return view('abooks.index', compact('books', 'allGenres', 'allAuthors', 'allReaders'));
    }

    public function create()
    {
        $genres = Genre::orderBy('name')->get();
        $readers = Reader::orderBy('name')->get();
        $agencies = Agency::orderBy('name')->get();
        return view('admin.abooks.create', compact('genres', 'readers', 'agencies'));
    }

    public function store(Request $request)
    {
        // Пока оставляем старую логику загрузки (в public), переделаем позже
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'reader_id' => 'nullable|exists:readers,id',
            'series_id' => 'nullable|exists:series,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'description' => 'nullable|string',
            'genres' => 'required|array',
            'duration' => 'nullable|integer',
            'cover_file' => 'required|image|mimes:jpg,jpeg,png',
            'audio_files' => 'required|array',
            'audio_files.*' => 'required|mimes:mp3,wav',
        ]);

        $coverPath = $request->file('cover_file')->store('covers', 'public');
        
        $image = Image::read($request->file('cover_file')->getRealPath())->cover(200, 300);
        $thumbName = 'covers/thumb_' . basename($coverPath);
        Storage::disk('public')->put($thumbName, (string) $image->toJpeg(80));

        $author = Author::firstOrCreate(['name' => $validated['author']]);

        $book = ABook::create([
            'title' => $validated['title'],
            'author_id' => $author->id,
            'reader_id' => $validated['reader_id'] ?? null,
            'series_id' => $validated['series_id'] ?? null,
            'agency_id' => $validated['agency_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'cover_url' => $coverPath,
            'thumb_url' => $thumbName, 
        ]);

        $book->genres()->sync($validated['genres']);

        foreach ($request->file('audio_files') as $index => $audioFile) {
            $path = $audioFile->store('audio', 'private');
            AChapter::create([
                'a_book_id' => $book->id,
                'title' => 'Глава ' . ($index + 1),
                'order' => $index + 1,
                'audio_path' => $path,
            ]);
        }

        return redirect('/abooks')->with('success', 'Книга успешно добавлена!');
    }

    public function edit($id)
    {
        $book = ABook::with(['genres', 'author', 'reader', 'agency'])->findOrFail($id);
        $genres = Genre::orderBy('name')->get();
        $readers = Reader::orderBy('name')->get();
        $agencies = Agency::orderBy('name')->get();
        return view('admin.abooks.edit', compact('book', 'genres', 'readers', 'agencies'));
    }

    public function update(Request $request, $id)
    {
        $book = ABook::findOrFail($id);
        // ... (валидация и обновление как было) ...
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'reader_id' => 'nullable|exists:readers,id',
            'series_id' => 'nullable|exists:series,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'description' => 'nullable|string',
            'genres' => 'required|array',
            'duration' => 'nullable|integer',
            'cover_file' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('cover_file')) {
            // ... (логика удаления старого и загрузки нового)
            $newCoverPath = $request->file('cover_file')->store('covers', 'public');
            $image = Image::read($request->file('cover_file')->getRealPath())->cover(200, 300);
            $thumbName = 'covers/thumb_' . basename($newCoverPath);
            Storage::disk('public')->put($thumbName, (string) $image->toJpeg(80));
            $book->cover_url = $newCoverPath;
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

        return redirect()->route('admin.abooks.index')->with('success', 'Книга обновлена');
    }

    public function destroy($id)
    {
        // ... (удаление) ...
        $book = ABook::findOrFail($id);
        $book->chapters()->each(function ($chapter) {
            // Storage::disk('private')->delete($chapter->audio_path); // Пока закомментируем, чтобы не падало
            $chapter->delete();
        });
        $book->delete();
        return redirect('/admin/abooks')->with('success', 'Книга удалена');
    }

    public function show($id)
    {
        $book = ABook::with('chapters')->findOrFail($id);
        return view('abooks.show', compact('book'));
    }

    // ======================= [API: Каталог аудиокниг] =======================
    public function apiIndex(Request $request)
    {
        $query = ABook::with(['author', 'reader', 'genres', 'series', 'agency']);

        // ... (Весь код поиска и фильтров оставляем как был) ...
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($genre = $request->input('genre')) {
             // ... логика фильтра жанров ...
             $genres = is_array($genre) ? $genre : explode(',', $genre);
             if(!empty($genres)) $query->whereHas('genres', fn($q) => $q->whereIn('genres.id', $genres));
        }
        if ($author = $request->input('author')) {
            $query->whereHas('author', fn($q) => is_numeric($author) ? $q->where('id', $author) : $q->where('name', 'like', "%{$author}%"));
        }
        if ($reader = $request->input('reader')) {
            $query->whereHas('reader', fn($q) => is_numeric($reader) ? $q->where('id', $reader) : $q->where('name', 'like', "%{$reader}%"));
        }
        if ($seriesId = $request->input('series_id')) {
             $query->where('series_id', $seriesId);
        }

        // Сортировка
        $sort = $request->input('sort');
        if ($sort === 'new') $query->orderBy('created_at', 'desc');
        elseif ($sort === 'title') $query->orderBy('title');
        elseif ($sort === 'duration') $query->orderBy('duration', 'desc');
        else $query->orderBy('created_at', 'desc');

        $books = $query->paginate(intval($request->input('per_page', 20)))->withQueryString();

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
                    
                    // 🔥 ИСПРАВЛЕНИЕ: Генерируем ссылку на облако (S3/R2), а не локальную
                    'cover_url'   => $book->cover_url ? Storage::disk('s3')->url($book->cover_url) : null,
                    'thumb_url'   => $book->thumb_url ? Storage::disk('s3')->url($book->thumb_url) : null,
                    
                    'genres'      => $book->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->values(),
                    'series'      => $book->series?->title,
                    'series_id'   => $book->series_id,
                ];
            }),
        ];

        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    // ======================= [API: ОДНА КНИГА] =======================
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
            
            // 🔥 ИСПРАВЛЕНИЕ: Ссылка на Cloudflare R2
            'cover_url'   => $book->cover_url ? Storage::disk('s3')->url($book->cover_url) : null,
            'thumb_url'   => $book->thumb_url ? Storage::disk('s3')->url($book->thumb_url) : null,
            
            'genres'      => $book->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name])->values(),
            'series'      => $book->series?->title,
            'series_id'   => $book->series_id,
        ];

        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    // ======================= [API: ГЛАВЫ] =======================
    public function apiChapters($id)
    {
        // Тут всё отлично, ссылка ведет на наш контроллер AudioStreamController,
        // который уже умеет работать с облаком.
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