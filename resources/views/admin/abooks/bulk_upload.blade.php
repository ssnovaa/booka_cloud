@extends('layouts.app') 

@section('content')
<div class="container py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-4">
            Масовий імпорт книг (R2/S3)
        </h2>

        {{-- Сообщения об успехе/ошибке --}}
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                {{ session('success') }}
            </div>
        @endif

        {{-- Таблица книг --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                @if(count($importList) > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Автор</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Назва</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Файли</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Обкладинка</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Дія</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($importList as $item)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item['author'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item['title'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item['files'] }} MP3</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($item['hasCover'])
                                            <span class="text-green-600 font-bold">✓ Є</span>
                                        @else
                                            <span class="text-red-500">✗ Немає</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <form action="{{ route('admin.abooks.import') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="folder_path" value="{{ $item['path'] }}">
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                Імпортувати
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500 text-center py-4">Папка 'incoming' порожня або не містить книг з MP3 файлами.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- 🔥 МОДАЛЬНОЕ ОКНО ПРОГРЕССА --}}
<div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="display: none; background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
    <div class="modal-dialog modal-dialog-centered" style="margin: 10% auto; max-width: 500px;">
        <div class="modal-content bg-white rounded-lg shadow-xl p-6">
            <div class="modal-header border-b pb-3 mb-3">
                <h5 class="modal-title text-lg font-bold">Імпорт книги...</h5>
            </div>
            <div class="modal-body">
                <p class="mb-2 text-gray-600">Будь ласка, зачекайте. Сервер обробляє аудіофайли.</p>
                
                {{-- Сама полоска --}}
                <div class="w-full bg-gray-200 rounded-full h-6 dark:bg-gray-700 mb-2">
                    <div id="progressBar" class="bg-blue-600 h-6 rounded-full text-center text-xs font-medium text-blue-100 p-0.5 leading-none transition-all duration-500" style="width: 0%"> 0%</div>
                </div>
                
                <p class="text-sm text-gray-500 mt-2 text-center" id="progressText">Ініціалізація...</p>
                
                {{-- КНОПКИ УПРАВЛЕНИЯ --}}
                <div class="mt-4 text-center">
                    {{-- Кнопка отмены (красная) --}}
                    <button id="btnCancel" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition">
                        Скасувати
                    </button>
                    
                    {{-- Кнопка готово (зеленая, скрыта) --}}
                    <a href="{{ route('admin.abooks.index') }}" id="btnFinish" class="hidden bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition">
                        Готово! Перейти до списку
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 🔥 СКРИПТ (ЗАПУСКАЕТСЯ ТОЛЬКО ЕСЛИ БЫЛ СТАРТ ИМПОРТА) --}}
@if(session('import_path'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Показываем модальное окно
        const modal = document.getElementById('progressModal');
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.classList.remove('fade'); // Убираем анимацию Bootstrap для надежности

        const folderPath = "{{ session('import_path') }}";
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const btnFinish = document.getElementById('btnFinish');
        const btnCancel = document.getElementById('btnCancel');

        // 2. Обработчик кнопки ОТМЕНА
        btnCancel.addEventListener('click', function() {
            if (!confirm('Ви точно хочете зупинити імпорт? Книга буде видалена, процес зупиниться.')) return;

            // Блокируем кнопку, чтобы не нажимали много раз
            btnCancel.disabled = true;
            btnCancel.innerText = "Зупиняємо...";
            btnCancel.classList.add('opacity-50', 'cursor-not-allowed');
            progressText.innerText = "Відправка команди на зупинку...";

            // Отправляем AJAX запрос на отмену
            fetch('{{ route('admin.abooks.import.cancel') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ folder_path: folderPath })
            })
            .then(response => response.json())
            .then(data => {
                alert('Імпорт скасовано. Сторінка оновиться.');
                window.location.href = "{{ route('admin.abooks.bulk-upload') }}"; // Перезагружаем страницу
            })
            .catch(err => {
                console.error(err);
                alert('Помилка при скасуванні.');
                btnCancel.disabled = false;
                btnCancel.innerText = "Скасувати";
            });
        });

        // 3. Функция опроса прогресса (каждые 2 секунды)
        let interval = setInterval(() => {
            fetch(`/admin/abooks/import/progress?path=${encodeURIComponent(folderPath)}`)
                .then(response => response.json())
                .then(data => {
                    const percent = data.progress;
                    
                    // Обновляем визуально
                    progressBar.style.width = percent + '%';
                    progressBar.innerText = percent + '%';
                    progressText.innerText = `Оброблено: ${percent}%`;

                    // Если 100% — меняем состояние
                    if (percent >= 100) {
                        clearInterval(interval);
                        
                        // Меняем цвет полоски на зеленый
                        progressBar.classList.remove('bg-blue-600');
                        progressBar.classList.add('bg-green-500');
                        progressText.innerText = "Імпорт завершено успішно!";
                        
                        // Скрываем кнопку отмены, показываем кнопку Готово
                        btnCancel.classList.add('hidden');
                        btnFinish.classList.remove('hidden');
                    }
                })
                .catch(err => console.error("Ошибка опроса прогресса:", err));
        }, 2000); 
    });
</script>
@endif

@endsection