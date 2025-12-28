@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Серии книг</h1>
        <a href="{{ route('admin.series.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            ➕ Добавить серию
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    <table class="min-w-full bg-white rounded shadow">
        <thead>
            <tr>
                <th class="p-2 border">ID</th>
                <th class="p-2 border">Название</th>
                <th class="p-2 border">Описание</th>
                <th class="p-2 border">Действия</th>
            </tr>
        </thead>
        <tbody>
            @foreach($series as $s)
                <tr>
                    <td class="p-2 border">{{ $s->id }}</td>
                    <td class="p-2 border">{{ $s->title }}</td>
                    <td class="p-2 border text-gray-500">{{ Str::limit($s->description, 60) }}</td>
                    <td class="p-2 border">
                        <a href="{{ route('admin.series.edit', $s) }}" class="text-yellow-600 hover:underline mr-2">✏️ Редактировать</a>
                        <form action="{{ route('admin.series.destroy', $s) }}" method="POST" class="inline"
                              onsubmit="return confirm('Удалить серию?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">🗑️ Удалить</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $series->links() }}
    </div>
</div>
@endsection
