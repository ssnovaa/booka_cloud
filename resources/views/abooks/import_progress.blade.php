{{-- resources/views/abooks/import_progress.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Импорт книг из FTP</h1>

    @if(isset($error))
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800 border border-red-300">
            <p class="font-semibold">Ошибка: {{ $error }}</p>
        </div>
    @elseif(isset($result))
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800 border border-green-300">
            <p class="font-semibold">Готово! Импортировано: {{ $result['imported'] }} из {{ $result['processed'] }}, пропущено: {{ $result['skipped'] }}.</p>
        </div>
    @endif

    <div class="mb-6">
        <h2 class="text-lg font-semibold mb-2">Прогресс</h2>
        <div class="space-y-2 bg-gray-50 border rounded p-3 max-h-[60vh] overflow-auto">
            @forelse($log as $entry)
                @php
                    $color = match($entry['level']) {
                        'error' => 'text-red-700',
                        'warning' => 'text-yellow-700',
                        'success' => 'text-green-700',
                        default => 'text-gray-800',
                    };
                @endphp
                <div class="{{ $color }}">{{ $entry['message'] }}</div>
            @empty
                <p class="text-gray-500">Лог пуст.</p>
            @endforelse
        </div>
    </div>

    <a href="{{ route('admin.abooks.index') }}" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Вернуться в каталог</a>
</div>
@endsection