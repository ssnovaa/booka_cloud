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
use getID3; // Бібліотека для аналізу MP3
use FFMpeg\FFMpeg; // Бібліотека для роботи з FFmpeg

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

    // Збереження (Конвертація в HLS та завантаження в R2)
    public function store(Request $request)
    {
        // 🔥 ОПТИМІЗАЦІЯ ДЛЯ ВЕЛИКИХ ФАЙЛІВ
        set_time_limit(0); // Знімаємо обмеження за часом виконання
        ini_set('memory_limit', '1024M'); // Виділяємо 1 ГБ оперативної пам'яті

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'reader_id' => 'nullable|exists:readers,id',
            'series_id' => 'nullable|exists:series,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'description' => 'nullable|string',
            'genres' => 'nullable|array',
            'genres.*' => 'integer|exists:genres,id',
            'cover_file' => 'required|image|mimes:jpg,jpeg,png',
            'audio_files' => 'required|array',
            'audio_files.*' => 'required|mimes:mp3,wav',
        ]);

        // 1. ОБКЛАДИНКИ (Публічний R2)
        $coverFile = $request->file('cover_file');
        $coverName = 'covers/' . time() . '_' . $coverFile->getClientOriginalName();
        Storage::disk('s3')->put($coverName, fopen($coverFile->getRealPath(), 'r+'), 'public');

        $image = Image::read($coverFile->getRealPath())->cover(200, 300);
        $thumbName = 'covers/thumb_' . basename($coverName);
        Storage::disk('s3')->put($thumbName, (string) $image->toJpeg(80), 'public');

        $authorName = $validated['author'] ?? 'Невідомий автор';
        $author = Author::firstOrCreate(['name' => $authorName]);

        $book = ABook::create([
            'title' => $validated['title'],
            'author_id' => $author->id,
            'reader_id' => $validated['reader_id'] ?? null,
            'series_id' => $validated['series_id'] ?? null,
            'agency_id' => $validated['agency_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'cover_url' => $coverName,
            'thumb_url' => $thumbName, 
        ]);

        if (!empty($validated['genres'])) {
            $book->genres()->sync($validated['genres']);
        }

        // 2. ОБРОБКА АУДІО (HLS + Приватний R2)
        $getID3 = new getID3();
        $totalSeconds = 0;

        foreach ($request->file('audio_files') as $index => $audioFile) {
            $chapterIndex = $index + 1;
            $tempPath = $audioFile->getRealPath();
            
            // Аналіз тривалості
            $fileInfo = $getID3->analyze($tempPath);
            $duration = (int) round($fileInfo['playtime_seconds'] ?? 0);
            $totalSeconds += $duration;

            // Створюємо тимчасову папку для сегментів HLS
            $localHlsFolder = storage_path("app/temp_hls/{$book->id}/{$chapterIndex}");
            if (!file_exists($localHlsFolder)) {
                mkdir($localHlsFolder, 0777, true);
            }

            $playlistName = "index.m3u8";
            $localPlaylistPath = "{$localHlsFolder}/{$playlistName}";

            // 🔥 ПРИСКОРЕНА НАРІЗКА (додано -threads 0 для максимальної швидкості)
            $cmd = "ffmpeg -i " . escapeshellarg($tempPath) . " -c:a libmp3lame -b:a 128k -map 0:0 -f hls -hls_time 10 -hls_list_size 0 -threads 0 -hls_segment_filename " . escapeshellarg("{$localHlsFolder}/seg_%03d.ts") . " " . escapeshellarg($localPlaylistPath) . " 2>&1";
            shell_exec($cmd);

            // Завантажуємо всі файли з тимчасової папки в R2
            if (file_exists($localPlaylistPath)) {
                $files = scandir($localHlsFolder);
                $cloudFolder = "audio/hls/{$book->id}/{$chapterIndex}";
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $cloudPath = "{$cloudFolder}/{$file}";
                    Storage::disk('s3_private')->put($cloudPath, fopen("{$localHlsFolder}/{$file}", 'r+'));
                }

                // Очищення тимчасових локальних файлів
                array_map('unlink', glob("{$localHlsFolder}/*.*"));
                rmdir($localHlsFolder);

                AChapter::create([
                    'a_book_id' => $book->id,
                    'title' => 'Глава ' . $chapterIndex,
                    'order' => $chapterIndex,
                    'audio_path' => "{$cloudFolder}/{$playlistName}",
                    'duration' => $duration,
                ]);
            }
        }

        // Оновлюємо тривалість книги в хвилинах
        $book->update(['duration' => (int) round($totalSeconds / 60)]);

        return redirect('/abooks')->with('success', 'Книгу успішно додано та конвертовано в HLS!');
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
        set_time_limit(0); 

        $book = ABook::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'reader_id' => 'nullable|exists:readers,id',
            'series_id' => 'nullable|exists:series,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'description' => 'nullable|string',
            'genres' => 'nullable|array',
            'genres.*' => 'integer|exists:genres,id',
            'cover_file' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('cover_file')) {
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

        $authorName = $validated['author'] ?? 'Невідомий автор';
        $author = Author::firstOrCreate(['name' => $authorName]);
        
        $book->author_id = $author->id;
        $book->reader_id = $validated['reader_id'] ?? null;
        $book->series_id = $validated['series_id'] ?? null;
        $book->agency_id = $validated['agency_id'] ?? null;
        $book->title = $validated['title'];
        $book->description = $validated['description'] ?? null;
        $book->save();

        if (isset($validated['genres'])) {
            $book->genres()->sync($validated['genres']);
        }

        return redirect()->route('admin.abooks.index')->with('success', 'Книгу оновлено');
    }

    // Видалення
    public function destroy($id)
    {
        $book = ABook::findOrFail($id);

        if ($book->cover_url) {
            Storage::disk('s3')->delete($book->cover_url);
        }
        if ($book->thumb_url) {
            Storage::disk('s3')->delete($book->thumb_url);
        }

        $book->chapters()->each(function ($chapter) {
            if ($chapter->audio_path) {
                if (str_ends_with($chapter->audio_path, '.m3u8')) {
                    Storage::disk('s3_private')->deleteDirectory(dirname($chapter->audio_path));
                } else {
                    Storage::disk('s3_private')->delete($chapter->audio_path);
                }
            }
            $chapter->delete();
        });

        $book->genres()->detach();
        $book->delete();

        return redirect('/admin/abooks')->with('success', 'Книгу та HLS-файли видалено');
    }

    // Перегляд книги (адмінка)
    public function show($id)
    {
        $book = ABook::with('chapters')->findOrFail($id);
        return view('abooks.show', compact('book'));
    }

    // ======================= [API: MOBILE APP] =======================

    public function apiIndex(Request $request)
    {
        $query = ABook::with(['author', 'reader', 'genres', 'series', 'agency']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

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

        if ($sort = $request->input('sort')) {
            if ($sort === 'new') {
                $query->orderBy('created_at', 'desc');
            } elseif ($sort === 'title') {
                $query->orderBy('title');
            } elseif ($sort === 'duration') {
                $query->orderBy('duration', 'desc');
            }
        } else {
            // 🔥 ВИПРАВЛЕННЯ: якщо сортування не обрано, показуємо спочатку нові книги
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
                    // 🔥 ВИПРАВЛЕННЯ: Додано перевірку на null, щоб Flutter не падав
                    'author'      => $book->author?->name ?? 'Невідомий автор',
                    'reader'      => $book->reader?->name,
                    'description' => $book->description,
                    'duration'    => $book->duration,
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

    public function apiShow($id)
    {
        $book = ABook::with(['author', 'reader', 'genres', 'series', 'agency'])->findOrFail($id);

        $result = [
            'id'          => $book->id,
            'title'       => $book->title,
            'author'      => $book->author?->name ?? 'Невідомий автор',
            'reader'      => $book->reader?->name,
            'description' => $book->description,
            'duration'    => $book->duration,
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

    public function apiChapters($id)
    {
        $book = ABook::findOrFail($id);

        $chapters = AChapter::where('a_book_id', $book->id)
            ->orderBy('order')
            ->get()
            ->map(function ($chapter) {
                // 🔥 ГІБРИДНА ЛОГІКА ПОСИЛАНЬ:
                $isHls = str_ends_with($chapter->audio_path, '.m3u8');
                $url = $isHls 
                    ? url("/audio/{$chapter->id}/index.m3u8") 
                    : url("/audio/{$chapter->id}");

                return [
                    'id'        => $chapter->id,
                    'duration'  => $chapter->duration,
                    'title'     => $chapter->title,
                    'order'     => $chapter->order,
                    'audio_url' => $chapter->audio_path ? $url : null,
                ];
            })->values();

        return response()->json($chapters, 200, [], JSON_UNESCAPED_UNICODE);
    }
}