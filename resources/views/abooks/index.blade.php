{{-- resources/views/abooks/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Каталог аудиокниг</h1>

    @auth
        @if(auth()->user()->is_admin)
            <div class="mb-6 border-b pb-6">
                {{-- Заменено: Кнопка Массовой загрузки с ПК вместо FTP --}}
                <a href="{{ route('admin.abooks.bulk-upload') }}" 
                   class="inline-block mb-2 mr-4 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 shadow transition font-bold">
                    📁 Массовая загрузка книг (с ПК)
                </a>

                <a href="{{ route('admin.abooks.create') }}"
                   class="inline-block mb-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                    ➕ Добавить одну книгу
                </a>

                <p class="text-sm text-gray-600 mt-2">
                    <strong>Массовая загрузка:</strong> выберите папку на своем компьютере. Система сама распознает названия, 
                    авторов, вычислит длительность аудио и защитит файлы в облаке R2.
                </p>
            </div>
        @endif
    @endauth

    {{-- Flash сообщение об успехе --}}
    @if(session('success'))
        <div class="mb-4 text-green-600 font-bold p-3 bg-green-50 border border-green-200 rounded">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mb-4 text-red-600 font-bold p-3 bg-red-50 border border-red-200 rounded">{{ session('error') }}</div>
    @endif

    {{-- 🔎 Форма поиска и фильтров --}}
    <form method="GET" action="{{ url('/abooks') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" id="filterForm">
        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Поиск..."
            class="border p-2 rounded w-full col-span-1 md:col-span-2"
            onkeypress="if(event.key === 'Enter') this.form.submit()"
        >

        <select name="genre" class="border p-2 rounded w-full" onchange="document.getElementById('filterForm').submit()">
            <option value="">Все жанры</option>
            @foreach($allGenres as $genre)
                <option value="{{ $genre->id }}" {{ request('genre') == $genre->id ? 'selected' : '' }}>
                    {{ $genre->name }}
                </option>
            @endforeach
        </select>

        <select name="sort" class="border p-2 rounded w-full" onchange="document.getElementById('filterForm').submit()">
            <option value="">Сортировка</option>
            <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>По названию</option>
            <option value="new" {{ request('sort') == 'new' ? 'selected' : '' }}>Сначала новые</option>
            <option value="duration" {{ request('sort') == 'duration' ? 'selected' : '' }}>По длительности</option>
        </select>
    </form>

    {{-- 📚 Список книг --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @foreach($books as $book)
            @include('partials.book_card', ['book' => $book])
        @endforeach
    </div>

    <div class="mt-6">
        {{ $books->links() }}
    </div>
</div>
@endsection