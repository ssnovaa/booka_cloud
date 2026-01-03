<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Імпорт з хмари (R2/S3)</h2>
                    <a href="{{ route('admin.abooks.index') }}" class="text-gray-600 hover:text-gray-900">&larr; Назад</a>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                    <div class="flex">
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                <strong>Правильна структура папок:</strong><br>
                                <code>incoming / Автор_Назва Книги / файли.mp3</code><br><br>
                                Наприклад: <code>incoming / Стівен Кінг_Воно / 01.mp3</code><br>
                                Система автоматично розділить назву по символу "<b>_</b>".
                            </p>
                        </div>
                    </div>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    @if(empty($importList))
                        <div class="text-center py-12 text-gray-500">
                            <p class="text-lg">Папка <code>incoming</code> порожня.</p>
                            <p class="text-sm mt-2">Завантажте папку виду <code>Автор_Назва</code> через FTP і оновіть сторінку.</p>
                        </div>
                    @else
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Розпізнаний Автор</th>
                                    <th class="py-3 px-6 text-left">Розпізнана Книга</th>
                                    <th class="py-3 px-6 text-center">Файлів</th>
                                    <th class="py-3 px-6 text-center">Дія</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                                @foreach($importList as $item)
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-6 text-left whitespace-nowrap font-medium">{{ $item['author'] }}</td>
                                        <td class="py-3 px-6 text-left">{{ $item['title'] }}</td>
                                        <td class="py-3 px-6 text-center">
                                            <span class="bg-gray-200 text-gray-700 py-1 px-3 rounded-full text-xs font-bold">{{ $item['files'] }} mp3</span>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <form action="{{ route('admin.abooks.import') }}" method="POST" onsubmit="return confirm('Імпортувати: {{ $item['author'] }} - {{ $item['title'] }}?')">
                                                @csrf
                                                <input type="hidden" name="folder_path" value="{{ $item['path'] }}">
                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow transition">
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