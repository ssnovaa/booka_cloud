@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Редактировать главу в "{{ $book->title }}"</h1>

    <form action="{{ route('admin.chapters.update', [$book->id, $chapter->id]) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block mb-1 font-semibold">Название главы:</label>
            <input type="text" name="title" required class="w-full border p-2 rounded" value="{{ old('title', $chapter->title) }}">
            @error('title')
                <p class="text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block mb-1 font-semibold">Заменить аудиофайл (mp3/wav):</label>
            <input type="file" name="audio_file" accept="audio/mp3,audio/wav">
            <p class="text-sm text-gray-500">Если не хотите менять — оставьте пустым.</p>
            @error('audio_file')
                <p class="text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Сохранить изменения</button>
        <a href="{{ route('abooks.show', $book->id) }}" class="ml-4 text-gray-600 hover:underline">Отмена</a>
    </form>
</div>
@endsection
