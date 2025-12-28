@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Чтецы</h1>

    {{-- Кнопка создания нового чтеца --}}
    <a href="{{ route('admin.readers.create') }}" class="mb-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        ➕ Добавить чтеца
    </a>

    {{-- Сообщение об успешном действии --}}
    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    {{-- Проверка есть ли чтецы --}}
    @if($readers->count() > 0)
        <table class="w-full border-collapse border border-gray-300">
            <thead>
                <tr>
                    <th class="border border-gray-300 px-4 py-2 text-left">ID</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Имя</th>
                    <th class="border border-gray-300 px-4 py-2 text-left">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach($readers as $reader)
                    <tr>
                        <td class="border border-gray-300 px-4 py-2">{{ $reader->id }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $reader->name }}</td>
                        <td class="border border-gray-300 px-4 py-2">
                            {{-- Ссылка на редактирование --}}
                            <a href="{{ route('admin.readers.edit', $reader->id) }}" class="text-blue-600 hover:underline mr-2">Редактировать</a>

                            {{-- Форма удаления --}}
                            <form action="{{ route('admin.readers.destroy', $reader->id) }}" method="POST" class="inline" onsubmit="return confirm('Удалить чтеца?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Пагинация --}}
        <div class="mt-6">
            {{ $readers->links() }}
        </div>
    @else
        {{-- Сообщение если чтецов нет --}}
        <p>Чтецы пока не добавлены.</p>
    @endif
</div>
@endsection
