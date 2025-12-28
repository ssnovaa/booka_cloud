@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Редактировать серию</h1>
    <form method="POST" action="{{ route('admin.series.update', $series) }}">
        @csrf
        @method('PUT')
        <div class="mb-4">
            <label class="block mb-1">Название серии *</label>
            <input name="title" class="w-full border rounded p-2" required value="{{ old('title', $series->title) }}">
            @error('title') <div class="text-red-500 text-sm">{{ $message }}</div> @enderror
        </div>
        <div class="mb-4">
            <label class="block mb-1">Описание</label>
            <textarea name="description" class="w-full border rounded p-2">{{ old('description', $series->description) }}</textarea>
        </div>
        <div class="mb-4">
            <label class="block mb-1">Обложка (URL)</label>
            <input name="cover_url" class="w-full border rounded p-2" value="{{ old('cover_url', $series->cover_url) }}">
        </div>
        <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded">Сохранить</button>
        <a href="{{ route('admin.series.index') }}" class="ml-4 text-gray-600 hover:underline">Назад</a>
    </form>
</div>
@endsection
