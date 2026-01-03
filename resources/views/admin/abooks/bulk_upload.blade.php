@extends('layouts.app')

@section('content')
    {{-- Заголовок страницы (внутри контента, так как layout не поддерживает slot header) --}}
    <div class="mb-6 pb-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">
            {{ __('Імпорт з хмари (R2/S3)') }}
        </h2>
        <a href="{{ route('admin.abooks.index') }}" class="text-blue-600 hover:text-blue-900 font-bold">
            &larr; Назад до книг
        </a>
    </div>

    <div class="py-2">
        <div class="max-w-full mx-auto">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                {{-- Повідомлення --}}
                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Знайдені папки в "incoming"</h3>
                </div>

                <div class="overflow-x-auto">
                    @if(empty($importList))
                        <div class="text-center py-12 text-gray-500 bg-gray-50 rounded border border-dashed border-gray-300">
                            <p class="text-lg">Папка <code>incoming</code> порожня або MP3 файли не знайдено.</p>
                            <p class="text-sm mt-2">Залийте папку з книгою (MP3 всередині) на R2 і оновіть сторінку.</p>
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Автор</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Книга</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Файли</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Обкладинка</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Дія</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($importList as $item)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $item['author'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $item['title'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $item['files'] }} MP3
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                            @if($item['hasCover'])
                                                <span class="text-green-600 font-bold" title="Знайдено">🖼️ Є</span>
                                            @else
                                                <span class="text-red-400" title="Не знайдено">❌ Немає</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form action="{{ route('admin.abooks.import') }}" method="POST" onsubmit="return confirm('Почати імпорт \'{{ $item['title'] }}\'?\n\nЦе займе деякий час (нарізка HLS). Не закривайте сторінку!');">
                                                @csrf
                                                <input type="hidden" name="folder_path" value="{{ $item['path'] }}">
                                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-900 text-white font-bold py-2 px-4 rounded shadow-sm">
                                                    Імпортувати
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

            </div>
        </div>
    </div>
@endsection