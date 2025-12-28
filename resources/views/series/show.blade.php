@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-4">{{ $series->title }}</h1>

    @if($series->description)
        <p class="mb-4 text-gray-700">{{ $series->description }}</p>
    @endif

    @if($series->cover_url)
        <img src="{{ asset('storage/' . $series->cover_url) }}" alt="Обложка серии" class="w-48 mb-6 rounded shadow">
    @endif

    <h2 class="text-xl font-semibold mb-3">Книги серии:</h2>
    @if($series->books->count())
        <ul class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($series->books as $book)
                <li class="border rounded p-4 bg-white shadow">
                    <a href="{{ route('abooks.show', $book->id) }}" class="text-lg font-bold hover:underline">{{ $book->title }}</a>
                    <div class="text-gray-600 text-sm mb-2">
                        Автор: {{ $book->author->name ?? 'Не указан' }}
                    </div>
                    @if($book->cover_url)
                        <img src="{{ asset('storage/' . $book->cover_url) }}" alt="Обложка" class="w-24 mb-2 rounded">
                    @endif
                    <div class="text-gray-500 text-xs">{{ Str::limit($book->description, 70) }}</div>
                </li>
            @endforeach
        </ul>
    @else
        <p>В этой серии пока нет книг.</p>
    @endif
</div>
@endsection
