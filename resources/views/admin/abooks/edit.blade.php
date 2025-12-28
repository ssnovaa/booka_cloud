@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Редактировать книгу: {{ $book->title }}</h1>
        <a href="{{ route('admin.abooks.index') }}" class="text-gray-600 hover:underline">← Назад к списку</a>
    </div>

    @php
        $selectedGenres = $book->genres->pluck('id')->toArray();
    @endphp

    <form action="{{ route('admin.abooks.update', $book->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- 1. Основная информация --}}
        <div class="bg-white p-6 rounded shadow-sm border border-gray-200 space-y-4">
            <div>
                <label class="block mb-1 font-semibold">Название:</label>
                <input type="text" name="title" value="{{ old('title', $book->title) }}" required class="w-full border p-2 rounded">
            </div>

            <div>
                <label class="block mb-1 font-semibold">Автор:</label>
                <input type="text" name="author" value="{{ old('author', $book->author->name ?? '') }}" required class="w-full border p-2 rounded">
            </div>

            {{-- 📚 Серия книги --}}
            <div>
                <label class="block mb-1 font-semibold">Серия:</label>
                <select name="series_id" class="w-full border p-2 rounded">
                    <option value="">Без серии</option>
                    @foreach(\App\Models\Series::orderBy('title')->get() as $series)
                        <option value="{{ $series->id }}"
                            @if(old('series_id', $book->series_id) == $series->id) selected @endif>
                            {{ $series->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block mb-1 font-semibold">Описание:</label>
                <textarea name="description" rows="4" class="w-full border p-2 rounded">{{ old('description', $book->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1 font-semibold">Текущая обложка:</label>
                    @if($book->cover_url)
                        <img src="{{ asset('storage/' . $book->cover_url) }}" alt="Обложка" class="w-32 mb-2 rounded shadow">
                    @else
                        <div class="w-32 h-48 bg-gray-100 flex items-center justify-center text-gray-400 rounded border">Нет фото</div>
                    @endif
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Заменить обложку:</label>
                    <input type="file" name="cover_file" accept="image/*" class="w-full border p-2 rounded">
                    <p class="text-sm text-gray-500 mt-1">Если не хотите менять — оставьте пустым.</p>
                    @error('cover_file')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="block mb-1 font-semibold">Жанры:</label>
                <div class="flex flex-wrap gap-4 p-3 bg-gray-50 rounded border">
                    @foreach($genres as $genre)
                        <label class="inline-flex items-center cursor-pointer hover:bg-gray-100 px-2 py-1 rounded transition">
                            <input type="checkbox" name="genres[]" value="{{ $genre->id }}"
                                {{ in_array($genre->id, $selectedGenres, true) ? 'checked' : '' }}
                                class="mr-2">
                            {{ $genre->name }}
                        </label>
                    @endforeach
                </div>
                @error('genres')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label class="block mb-1 font-semibold">Длительность:</label>
                <p class="text-gray-700">{{ $book->formattedDuration() }} ({{ $book->duration ?? 0 }} мин)</p>
            </div>
        </div>

{{-- 💰 Финансовая информация (Выбор из справочника) --}}
        <div class="bg-purple-50 p-6 rounded shadow-sm border border-purple-200">
            <h3 class="text-lg font-bold text-purple-900 mb-2">💰 Правообладатель</h3>
            <p class="text-sm text-purple-700 mb-4 border-b border-purple-200 pb-2">
                Выберите Агентство/Издательство из списка. <br>
                Если выбрано "Нет (Автор)", получателем считается Автор книги.
            </p>

            <div>
                <label for="agency_id" class="block mb-1 font-semibold text-purple-900">Агентство:</label>
                <select name="agency_id" id="agency_id" class="w-full border border-purple-300 p-2 rounded focus:ring-purple-500">
                    <option value="">-- Нет (Деньги получает Автор) --</option>
                    @foreach($agencies as $agency)
                        <option value="{{ $agency->id }}" 
                            @if(old('agency_id', $book->agency_id) == $agency->id) selected @endif>
                            {{ $agency->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-purple-600 mt-2">
                    Нет в списке? <a href="{{ route('admin.agencies.create') }}" target="_blank" class="underline font-bold">Создать новое агентство</a>
                </p>
            </div>
            
            {{-- Поле Реквизиты можно убрать, так как оно теперь берется из Агентства автоматически --}}
        </div>

        {{-- Кнопки сохранения --}}
        <div class="flex items-center gap-4 pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 shadow transition">
                💾 Сохранить изменения
            </button>
            <a href="{{ route('admin.abooks.index') }}" class="text-gray-600 hover:text-gray-900">Отмена</a>
        </div>
    </form>

    {{-- === Управление главами книги === --}}
    <hr class="my-10 border-gray-300">

    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">📂 Главы книги</h2>
        <a href="{{ route('admin.chapters.create', ['book' => $book->id]) }}"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 shadow transition flex items-center gap-2">
            <span>➕</span> Добавить главу
        </a>
    </div>

    @if($book->chapters->count())
        <div class="bg-white rounded shadow overflow-hidden">
            <table class="w-full border-collapse">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="border-b px-4 py-3 text-left w-16">#</th>
                        <th class="border-b px-4 py-3 text-left">Название главы</th>
                        <th class="border-b px-4 py-3 text-left">Аудиофайл</th>
                        <th class="border-b px-4 py-3 text-center w-24">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($book->chapters as $chapter)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="border-b px-4 py-3 text-gray-500">{{ $chapter->order }}</td>
                            <td class="border-b px-4 py-3 font-medium">{{ $chapter->title }}</td>
                            <td class="border-b px-4 py-3">
                                @if($chapter->audio_path)
                                    <a href="{{ route('audio.stream', $chapter->id) }}" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
                                        ▶️ Слушать ({{ $chapter->formattedDuration() }})
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Нет файла</span>
                                @endif
                            </td>
                            <td class="border-b px-4 py-3 text-center">
                                <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.chapters.edit', [$book->id, $chapter->id]) }}" class="text-blue-500 hover:text-blue-700" title="Редактировать">✏️</a>
                                    <form action="{{ route('admin.chapters.destroy', [$book->id, $chapter->id]) }}" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить главу?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700" title="Удалить">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded text-yellow-800">
            У этой книги пока нет глав. Нажмите "Добавить главу", чтобы загрузить аудио.
        </div>
    @endif

</div>
@endsection