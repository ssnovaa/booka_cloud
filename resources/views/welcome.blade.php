@extends('layouts.app')

@section('content')
<div class="bg-white min-h-screen py-8">
    <div class="container mx-auto px-4 flex flex-col lg:flex-row gap-8">

        {{-- 📚 Контентна частина зліва --}}
        <div class="w-full lg:w-3/4 space-y-12">
  {{-- 🧱 Сітка карток із реальними книгами --}}
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($books as $book)
                    @include('partials.book_card', ['book' => $book])
                @empty
                    <p class="text-gray-500">Книги не знайдено.</p>
                @endforelse
            </div>
        </div>

 {{-- 🎯 Права колонка: Жанрі та коментарі --}}
        <aside class="w-full lg:w-1/4 space-y-8">
            {{-- Жанри --}}
            <div>
                <h3 class="text-lg font-semibold mb-2">Жанри</h3>
                @if($genres->count())
                    <ul class="text-sm text-gray-700 space-y-1">
                        @foreach($genres as $genre)
                            <li>
                                <a href="{{ route('abooks.index', ['genre' => $genre->id]) }}" class="flex justify-between hover:text-blue-600">
                                    {{ $genre->name }}
                                    <span class="text-gray-400">{{ $genre->books_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-500">Жанри відсутні</p>
                @endif
                <a href="{{ route('genres.index') }}" class="mt-2 inline-block text-sm text-blue-600 hover:underline">Усі Жанрі →</a>
            </div>

        </aside>
    </div>
</div>
@endsection
