@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Добавить аудиокнигу</h1>

    <form action="{{ route('admin.abooks.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1 font-semibold">Название:</label>
            <input type="text" name="title" required class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Автор:</label>
            <input type="text" name="author" required class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Чтец (исполнитель):</label>
            <select name="reader_id" class="w-full border p-2 rounded">
                <option value="">-- Выберите чтеца --</option>
                @foreach($readers as $reader)
                    <option value="{{ $reader->id }}">{{ $reader->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- 📚 Серия книги --}}
        <div>
            <label class="block mb-1 font-semibold">Серия:</label>
            <select name="series_id" class="w-full border p-2 rounded">
                <option value="">Без серии</option>
                @foreach(\App\Models\Series::orderBy('title')->get() as $series)
                    <option value="{{ $series->id }}">{{ $series->title }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Описание:</label>
            <textarea name="description" rows="4" class="w-full border p-2 rounded"></textarea>
        </div>

        {{-- 🔁 Выбор жанров из базы --}}
        <div>
            <label class="block mb-1 font-semibold">Жанры:</label>
            <div class="flex flex-wrap gap-4">
                @foreach($genres as $genre)
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="genres[]" value="{{ $genre->id }}" class="mr-2">
                        {{ $genre->name }}
                    </label>
                @endforeach
            </div>
            @error('genres')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block mb-1 font-semibold">Длительность (в минутах):</label>
            <input type="number" name="duration" class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Обложка (jpg/png):</label>
            <input type="file" name="cover_file" accept="image/*" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Аудиофайлы глав (mp3/wav):</label>
            <input type="file" name="audio_files[]" accept="audio/mp3,audio/wav" multiple required>
        </div>

        <div>
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                ➕ Добавить книгу
            </button>
        </div>
    </form>
</div>
@endsection
