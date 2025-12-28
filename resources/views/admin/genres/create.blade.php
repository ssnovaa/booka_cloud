{{-- resources/views/admin/genres/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6 max-w-xl">
    <h1 class="text-2xl font-bold mb-6">Добавить жанр</h1>

    @if (session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.genres.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div>
            <label class="block mb-1 font-semibold">Название жанра</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border p-2 rounded" placeholder="Например: Детективи">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Картинка жанра</label>
            <input type="file" name="image" accept="image/*" class="w-full border p-2 rounded">
            <p class="text-sm text-gray-500 mt-1">Допустимо: JPEG, PNG, WEBP, AVIF. До 3 МБ.</p>
            @error('image') <div class="text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex gap-4">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                💾 Сохранить
            </button>
            <a href="{{ route('admin.genres.index') }}" class="text-gray-600 hover:underline">Отмена</a>
        </div>
    </form>
</div>
@endsection
