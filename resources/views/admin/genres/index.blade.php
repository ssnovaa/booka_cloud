{{-- resources/views/admin/genres/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Жанры</h1>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    <div class="mb-4">
        <a href="{{ route('admin.genres.create') }}"
           class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            ➕ Добавить жанр
        </a>
    </div>

    <div class="overflow-x-auto border rounded">
        <table class="min-w-full table-auto">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">ID</th>
                    <th class="px-4 py-2 text-left">Картинка</th>
                    <th class="px-4 py-2 text-left">Название</th>
                    <th class="px-4 py-2 text-left">Книг</th>
                    <th class="px-4 py-2 text-left">Действия</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($genres as $genre)
                <tr class="border-t">
                    <td class="px-4 py-2">{{ $genre->id }}</td>
                    <td class="px-4 py-2">
                        @if($genre->image_url)
                            <img src="{{ $genre->image_url }}" alt="img"
                                 class="rounded" style="width:64px;height:64px;object-fit:cover;">
                        @else
                            <span class="text-gray-500">нет</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ $genre->name }}</td>
                    <td class="px-4 py-2">
                        {{ $genre->books_count ?? ($genre->relationLoaded('books') ? $genre->books->count() : '—') }}
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.genres.edit', $genre->id) }}"
                               class="text-blue-600 hover:underline">
                                Изменить
                            </a>
                            <form action="{{ route('admin.genres.destroy', $genre->id) }}"
                                  method="POST"
                                  onsubmit="return confirm('Удалить жанр «{{ $genre->name }}»?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">
                                    Удалить
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr class="border-t">
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                        Жанров пока нет.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
