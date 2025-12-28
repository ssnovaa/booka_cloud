{{-- resources/views/abooks/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Каталог аудиокниг</h1>

    @auth
        @if(auth()->user()->is_admin)
            {{-- Кнопка Импорта из FTP --}}
            <form action="{{ route('admin.abooks.import') }}" method="POST" class="inline-block mb-4 mr-4">
                @csrf
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 shadow">
                    🚀 Импортировать книги из FTP
                </button>
            </form>
            <a href="{{ route('admin.abooks.create') }}"
               class="inline-block mb-6 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                ➕ Добавить книгу
            </a>
        @endif
    @endauth

    {{-- Flash сообщение об успехе --}}
    @if(session('success'))
        <div class="mb-4 text-green-600 font-bold">{{ session('success') }}</div>
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

        {{-- Убираем кнопку фильтрации, т.к. отправка происходит автоматически --}}
        {{-- <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Фильтровать</button> --}}
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
