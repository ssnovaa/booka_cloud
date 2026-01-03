@extends('layouts.app') 

@section('content')
<div class="container py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight mb-4">
            Масовий імпорт книг (R2/S3)
        </h2>

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
                                {{-- Генерируем уникальный ID для строки на основе пути --}}
                                @php 
                                    $rowId = md5($item['path']); 
                                    // Проверяем, является ли эта книга той, что сейчас импортируется
                                    $isProcessing = isset($activeImport) && $activeImport['path'] === $item['path'];
                                @endphp

                                {{-- ОСНОВНАЯ СТРОКА С ИНФОРМАЦИЕЙ --}}
                                <tr id="row-main-{{ $rowId }}" class="{{ $isProcessing ? 'bg-blue-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $item['author'] }}</td>
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
                                        {{-- Кнопка "Импортировать" (скрываем, если процесс уже идет) --}}
                                        <form action="{{ route('admin.abooks.import') }}" method="POST" class="{{ $isProcessing ? 'hidden' : '' }}" id="form-{{ $rowId }}">
                                            @csrf
                                            <input type="hidden" name="folder_path" value="{{ $item['path'] }}">
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-xs uppercase tracking-widest">
                                                Імпорт
                                            </button>
                                        </form>

                                        {{-- Если процесс уже идет при загрузке страницы, показываем статус --}}
                                        <div id="status-badge-{{ $rowId }}" class="{{ $isProcessing ? '' : 'hidden' }}">
                                            <span class="text-blue-600 font-bold animate-pulse">ОБРОБКА...</span>
                                        </div>
                                    </td>
                                </tr>

                                {{-- СКРЫТАЯ СТРОКА ПРОГРЕССА (раскрывается JS-ом) --}}
                                <tr id="row-progress-{{ $rowId }}" class="{{ $isProcessing ? '' : 'hidden' }} bg-gray-50 shadow-inner">
                                    <td colspan="5" class="px-6 py-4">
                                        <div class="flex items-center justify-between">
                                            <div class="w-full mr-4">
                                                <div class="flex justify-between mb-1">
                                                    <span class="text-sm font-medium text-blue-700 dark:text-white" id="progress-text-{{ $rowId }}">Ініціалізація...</span>
                                                    <span class="text-sm font-medium text-blue-700 dark:text-white" id="progress-percent-{{ $rowId }}">0%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                                    <div id="progress-bar-{{ $rowId }}" class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" style="width: {{ $isProcessing ? $activeImport['progress'] : 0 }}%"></div>
                                                </div>
                                            </div>
                                            
                                            {{-- Кнопка отмены --}}
                                            <button type="button" 
                                                    id="btn-cancel-{{ $rowId }}"
                                                    onclick="cancelImport('{{ $item['path'] }}', '{{ $rowId }}')"
                                                    class="text-red-600 hover:text-red-900 font-bold text-sm border border-red-200 hover:bg-red-50 px-3 py-1 rounded">
                                                Стоп
                                            </button>
                                        </div>
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

{{-- ЛОГИКА JS --}}
@php
    // Определяем, какую книгу нужно мониторить при загрузке страницы
    $activePath = session('import_path') ?? ($activeImport['path'] ?? null);
    $activeHash = $activePath ? md5($activePath) : null;
@endphp

@if($activePath)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const activePath = "{{ $activePath }}";
        const rowId = "{{ $activeHash }}"; // ID строки (хеш)
        
        startMonitoring(activePath, rowId);
    });
</script>
@endif

<script>
    function startMonitoring(folderPath, rowId) {
        // 1. Показываем строку прогресса, скрываем кнопку
        const progressRow = document.getElementById(`row-progress-${rowId}`);
        const mainRow = document.getElementById(`row-main-${rowId}`);
        const formBtn = document.getElementById(`form-${rowId}`);
        const statusBadge = document.getElementById(`status-badge-${rowId}`);
        
        const progressBar = document.getElementById(`progress-bar-${rowId}`);
        const progressPercent = document.getElementById(`progress-percent-${rowId}`);
        const progressText = document.getElementById(`progress-text-${rowId}`);
        const btnCancel = document.getElementById(`btn-cancel-${rowId}`);

        if(progressRow) progressRow.classList.remove('hidden');
        if(formBtn) formBtn.classList.add('hidden');
        if(statusBadge) statusBadge.classList.remove('hidden');
        if(mainRow) mainRow.classList.add('bg-blue-50'); // Подсветка активной строки

        // 2. Запускаем интервал
        let interval = setInterval(() => {
            // Используем fetch к нашему API
            fetch(`/admin/abooks/import/progress?path=${encodeURIComponent(folderPath)}`)
                .then(response => response.json())
                .then(data => {
                    const percent = data.progress;
                    
                    // Обновляем UI
                    if (progressBar) progressBar.style.width = percent + '%';
                    if (progressPercent) progressPercent.innerText = percent + '%';
                    if (progressText) progressText.innerText = `Обробка: ${percent}%`;

                    // Если завершено (100%)
                    if (percent >= 100) {
                        clearInterval(interval);
                        
                        if(progressBar) {
                            progressBar.classList.remove('bg-blue-600');
                            progressBar.classList.add('bg-green-500');
                        }
                        if(progressText) {
                            progressText.innerText = "Успішно імпортовано!";
                            progressText.classList.add('text-green-600');
                        }
                        if(btnCancel) btnCancel.classList.add('hidden'); // Убираем кнопку отмены
                        
                        // Через 2 секунды перезагружаем страницу, чтобы книга исчезла из списка
                        setTimeout(() => {
                            window.location.href = "{{ route('admin.abooks.bulk-upload') }}"; 
                        }, 2000);
                    }
                })
                .catch(err => {
                    console.error("Ошибка мониторинга:", err);
                    // Не останавливаем интервал сразу, вдруг это просто сбой сети
                });
        }, 2000); // Опрос каждые 2 секунды
    }

    // Функция отмены (вызывается по клику на кнопку "Стоп")
    function cancelImport(folderPath, rowId) {
        if (!confirm('Ви точно хочете зупинити імпорт цієї книги?')) return;

        const btnCancel = document.getElementById(`btn-cancel-${rowId}`);
        const progressText = document.getElementById(`progress-text-${rowId}`);
        
        if(btnCancel) {
            btnCancel.disabled = true;
            btnCancel.innerText = "Зупиняємо...";
        }
        if(progressText) progressText.innerText = "Відправка команди скасування...";

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
            alert('Імпорт скасовано.');
            window.location.reload(); // Перезагружаем страницу, чтобы сбросить вид
        })
        .catch(err => {
            console.error(err);
            alert('Помилка при скасуванні.');
            if(btnCancel) btnCancel.disabled = false;
        });
    }
</script>
@endsection