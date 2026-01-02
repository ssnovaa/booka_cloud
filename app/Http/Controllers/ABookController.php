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
        // 🔥 ОПТИМІЗАЦІЯ: Знімаємо ліміти часу для Railway та FFmpeg
        set_time_limit(0); 
        ini_set('memory_limit', '1024M'); 

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

        // 1. ОБКЛАДИНКИ
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

        // 2. ОБРОБКА АУДІО
        $getID3 = new getID3();
        $totalSeconds = 0;

        foreach ($request->file('audio_files') as $index => $audioFile) {
            $chapterIndex = $index + 1;
            $tempPath = $audioFile->getRealPath();
            
            $fileInfo = $getID3->analyze($tempPath);
            $duration = (int) round($fileInfo['playtime_seconds'] ?? 0);
            $totalSeconds += $duration;

            $localHlsFolder = storage_path("app/temp_hls/{$book->id}/{$chapterIndex}");
            if (!file_exists($localHlsFolder)) {
                mkdir($localHlsFolder, 0777, true);
            }

            $playlistName = "index.m3u8";
            $localPlaylistPath = "{$localHlsFolder}/{$playlistName}";

            // 🔥 ШВИДКИЙ FFmpeg (додано -preset superfast та -threads 0)
            $cmd = "ffmpeg -i " . escapeshellarg($tempPath) . " -c:a libmp3lame -b:a 128k -map 0:0 -f hls -hls_time 10 -hls_list_size 0 -threads 0 -preset superfast -hls_segment_filename " . escapeshellarg("{$localHlsFolder}/seg_%03d.ts") . " " . escapeshellarg($localPlaylistPath) . " 2>&1";
            shell_exec($cmd);

            if (file_exists($localPlaylistPath)) {
                $files = scandir($localHlsFolder);
                $cloudFolder = "audio/hls/{$book->id}/{$chapterIndex}";
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $cloudPath = "{$cloudFolder}/{$file}";
                    Storage::disk('s3_private')->put($cloudPath, fopen("{$localHlsFolder}/{$file}", 'r+'));
                }

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

        $book->update(['duration' => (int) round($totalSeconds / 60)]);

        return redirect('/abooks')->with('success', 'Книгу успішно додано!');
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
            if ($book->cover_url) Storage::disk('s3')->delete($book->cover_url);
            if ($book->thumb_url) Storage::disk('s3')->delete($book->thumb_url);

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

        if ($book->cover_url) Storage::disk('s3')->delete($book->cover_url);
        if ($book->thumb_url) Storage::disk('s3')->delete($book->thumb_url);

        $book->chapters()->each(function ($chapter) {
            if ($chapter->audio_path) {
                // 🔥 БЕЗПЕЧНЕ ВИДАЛЕННЯ: якщо це HLS (.m3u8), видаляємо папку, якщо MP3 — тільки файл
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

        return redirect('/admin/abooks')->with('success', 'Книгу видалено');
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
        $books = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'current_page' => $books->currentPage(),
            'total' => $books->total(),
            'data' => $books->map(function ($book) {
                return [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author?->name,
                    'cover_url' => $book->cover_url ? Storage::disk('s3')->url($book->cover_url) : null,
                    'duration' => $book->duration,
                ];
            }),
        ]);
    }

    public function apiShow($id)
    {
        $book = ABook::with(['author', 'reader', 'genres'])->findOrFail($id);
        return response()->json([
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author?->name,
            'cover_url' => $book->cover_url ? Storage::disk('s3')->url($book->cover_url) : null,
            'description' => $book->description,
        ]);
    }

    /**
     * 🔥 ГІБРИДНІ ПОСИЛАННЯ ДЛЯ ПЛЕЄРА
     */
    public function apiChapters($id)
    {
        $chapters = AChapter::where('a_book_id', $id)->orderBy('order')->get();

        $data = $chapters->map(function ($chapter) {
            // Перевіряємо формат у базі
            $isHls = str_ends_with($chapter->audio_path, '.m3u8');
            
            // Якщо HLS — додаємо в кінець плейлист, щоб плеєр зрозумів формат
            // Якщо MP3 — даємо базове посилання
            $url = $isHls 
                ? url("/audio/{$chapter->id}/index.m3u8") 
                : url("/audio/{$chapter->id}");

            return [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'duration' => $chapter->duration,
                'order' => $chapter->order,
                'audio_url' => $url,
            ];
        });

        return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE);
    }
}