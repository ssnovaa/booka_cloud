@extends('layouts.app')

@section('content')
<!DOCTYPE html>
<html>
<head>
    <title>Админка — Список книг</title>
</head>
<body>
    <h1>Управление аудиокнигами</h1>

    <a href="/admin/abooks/create">➕ Добавить книгу</a>

    <ul>
        @foreach ($books as $book)
            <li style="margin-bottom: 30px;">
                <h3>{{ $book->title }} — {{ $book->author }}</h3>

                @if(is_array($book->genres))
                    <p><strong>Жанры:</strong> {{ implode(', ', $book->genres) }}</p>
                @else
                    <p><strong>Жанры:</strong> {{ $book->genres }}</p>
                @endif

                <p><strong>Длительность:</strong> {{ $book->formattedDuration() }}</p>
                <p>{{ $book->description }}</p>

                @if($book->cover_url)
                    <img src="{{ asset('storage/' . $book->cover_url) }}" alt="Обложка" width="150">
                @endif

                <form action="/admin/abooks/{{ $book->id }}" method="POST" onsubmit="return confirm('Удалить книгу?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit">❌ Удалить</button>
                </form>

                <hr>
            </li>
        @endforeach
    </ul>
</body>
</html>

@endsection
