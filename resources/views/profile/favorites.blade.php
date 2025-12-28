@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Мои избранные книги</h1>

    @if ($books->isEmpty())
        <p>У вас пока нет избранных книг.</p>
    @else
        <ul>
            @foreach ($books as $book)
                <li style="margin-bottom: 40px;">
                    <h3>
                        <a href="/abooks/{{ $book->id }}">
                            {{ $book->title }} — {{ $book->author }}
                        </a>
                    </h3>

                    @if(is_array($book->genres))
                        <p><strong>Жанры:</strong> {{ implode(', ', $book->genres) }}</p>
                    @else
                        <p><strong>Жанры:</strong> {{ $book->genres }}</p>
                    @endif

                    <p><strong>Длительность:</strong> {{ $book->duration }} хв</p>
                    <p>{{ $book->description }}</p>

                    @if($book->cover_url)
                        <img src="{{ asset('storage/' . $book->cover_url) }}" alt="Обложка" width="200">
                    @endif

                    <form method="POST" action="{{ route('favorites.toggle', $book->id) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-red-500">Убрать из избранного</button>
                    </form>

                    <hr>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $books->links() }}</div>
    @endif
</div>
@endsection
