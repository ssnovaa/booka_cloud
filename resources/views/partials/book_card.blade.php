{{-- resources/views/partials/book_card.blade.php --}}
<div class="relative bg-white border border-gray-200 rounded-lg shadow hover:shadow-md transition">

    {{-- Обложка --}}
    <div class="relative w-44 h-64 overflow-hidden mx-auto rounded-t-lg bg-white">
        @if ($book->cover_url)
            <a href="{{ route('abooks.show', $book->id) }}" class="block relative z-10">
                <img src="{{ asset('storage/' . $book->cover_url) }}"
                     alt="Обложка книги {{ $book->title }}"
                     class="w-full h-full object-cover object-center">
            </a>
        @else
            <div class="w-full h-full bg-gray-100 flex items-center justify-center text-sm text-gray-400">
                Обложка
            </div>
        @endif

        {{-- ✏️ Кнопки редактирования и удаления (только для админов) --}}
        @auth
            @if(auth()->user()?->is_admin)
                <div class="absolute top-2 right-2 flex space-x-2 z-50 pointer-events-auto">
                    <a href="{{ route('admin.abooks.edit', $book->id) }}"
                       class="bg-yellow-400 text-black text-xs px-2 py-1 rounded hover:bg-yellow-500 shadow cursor-pointer">
                        ✏️ Редактировать
                    </a>

                    <form action="{{ route('admin.abooks.destroy', $book->id) }}" method="POST"
                          onsubmit="return confirm('Удалить книгу?');"
                          class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="bg-red-500 text-white text-xs px-2 py-1 rounded hover:bg-red-600 shadow cursor-pointer">
                            🗑️ Удалить
                        </button>
                    </form>
                </div>
            @endif
        @endauth

        {{-- ⭐ Кнопка-избранное --}}
        <button class="absolute bottom-2 right-12 bg-white rounded-full shadow p-1 hover:text-yellow-500 transition z-20" aria-label="Добавить в избранное">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.12 3.446a1 1 0 00.95.69h3.622c.969 0 1.371 1.24.588 1.81l-2.93 2.127a1 1 0 00-.364 1.118l1.12 3.446c.3.921-.755 1.688-1.538 1.118l-2.93-2.127a1 1 0 00-1.176 0l-2.93 2.127c-.783.57-1.838-.197-1.538-1.118l1.12-3.446a1 1 0 00-.364-1.118L2.781 8.873c-.783-.57-.38-1.81.588-1.81h3.622a1 1 0 00.95-.69l1.12-3.446z"/>
            </svg>
        </button>
    </div>

    {{-- Контент --}}
    <div class="p-3">
        {{-- Жанры --}}
        @if($book->genres && $book->genres->count())
            <div class="mb-1 flex flex-wrap gap-1" aria-label="Жанры книги">
                @foreach($book->genres as $genre)
                    <span class="text-xs bg-gray-200 rounded px-2 py-0.5">{{ $genre->name }}</span>
                @endforeach
            </div>
        @endif

        {{-- Название --}}
        <h3 class="text-sm font-semibold text-gray-900 leading-tight mb-1 line-clamp-2">
            <a href="{{ route('abooks.show', $book->id) }}" class="hover:underline" aria-label="Перейти к книге {{ $book->title }}">
                {{ $book->title }}
            </a>
        </h3>

        {{-- Автор --}}
        <p class="text-xs text-gray-600">📖 {{ $book->author->name ?? 'Автор не указан' }}</p>

        {{-- Чтец --}}
        @if($book->reader)
            <p class="text-xs text-gray-600">🎙 {{ $book->reader }}</p>
        @endif

        {{-- Длительность --}}
        <p class="text-xs text-gray-400 mt-1">⏱ {{ $book->formattedDuration() }}</p>
    </div>
</div>
