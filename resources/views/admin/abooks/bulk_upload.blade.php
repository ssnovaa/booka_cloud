<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Імпорт з хмари (R2/S3)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Список папок в "incoming"</h2>
                    <a href="{{ route('admin.abooks.index') }}" class="text-gray-600 hover:text-gray-900 font-bold">&larr; Назад до книг</a>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                <strong>Інструкція:</strong><br>
                                1. Залийте папки через CyberDuck в папку <code>incoming</code>.<br>
                                2. Правильна назва папки: <code>Автор_Назва Книги</code> (наприклад: <code>Стівен Кінг_Воно</code>).<br>
                                3. Система сама знайде MP3 файли та картинку обкладинки всередині.
                            </p>
                        </div>
                    </div>
                </div>

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

                <div class="overflow-x-auto">
                    @if(empty($importList))
                        <div class="text-center py-12 text-gray-500">
                            <p class="text-lg">Папка <code>incoming</code> порожня.</p>
                            <p class="text-sm mt-2">Підключіться до R2 і завантажте папки.</p>
                        </div>
                    @else
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Автор</th>
                                    <th class="py-3 px-6 text-left">Назва книги</th>
                                    <th class="py-3 px-6 text-center">Файли</th>
                                    <th class="py-3 px-6 text-center">Обкл.</th>
                                    <th class="py-3 px-6 text-center">Дія</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                                @foreach($importList as $item)
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-6 text-left whitespace-nowrap font-medium">{{ $item['author'] }}</td>
                                        <td class="py-3 px-6 text-left">{{ $item['title'] }}</td>
                                        <td class="py-3 px-6 text-center">
                                            <span class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-xs font-bold">
                                                {{ $item['files'] }} mp3
                                            </span>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            @if($item['hasCover'])
                                                <span title="Є обкладинка">🖼️ ✅</span>
                                            @else
                                                <span title="Без обкладинки" class="text-gray-300">⬜</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <form action="{{ route('admin.abooks.import') }}" method="POST" onsubmit="return confirm('Почати імпорт \'{{ $item['title'] }}\'?\n\nЦе займе час (HLS нарізка). Не закривайте вкладку!')">
                                                @csrf
                                                <input type="hidden" name="folder_path" value="{{ $item['path'] }}">
                                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded shadow transition transform hover:scale-105">
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
</x-app-layout>