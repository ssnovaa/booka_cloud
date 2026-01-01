@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Импорт книг из FTP</h1>

    <div id="status-badge" class="mb-4 p-3 rounded bg-blue-100 text-blue-800 border border-blue-300">
        <p class="font-semibold" id="status-text">Подготовка к импорту...</p>
    </div>

    <div class="mb-6">
        <div class="flex justify-between items-center mb-2">
            <h2 class="text-lg font-semibold">Живой лог прогресса</h2>
            <span id="spinner" class="animate-spin h-5 w-5 border-2 border-blue-600 border-t-transparent rounded-full"></span>
        </div>
        <div id="log-container" class="space-y-1 bg-gray-900 text-gray-100 font-mono text-sm border rounded p-4 h-[60vh] overflow-auto">
            <div class="text-gray-500">// Ожидание соединения с сервером...</div>
        </div>
    </div>

    <a href="{{ route('admin.abooks.index') }}" class="inline-block bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Вернуться в каталог</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const logContainer = document.getElementById('log-container');
        const statusText = document.getElementById('status-text');
        const statusBadge = document.getElementById('status-badge');
        const spinner = document.getElementById('spinner');

        // Запускаем стриминг
        fetch("{{ route('admin.abooks.run') }}")
            .then(response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                function read() {
                    return reader.read().then(({ done, value }) => {
                        if (done) return;

                        const chunk = decoder.decode(value, { stream: true });
                        const lines = chunk.split("\n");

                        lines.forEach(line => {
                            if (!line.trim()) return;
                            try {
                                const data = JSON.parse(line);
                                handleMessage(data);
                            } catch (e) { console.error("Ошибка парсинга:", line); }
                        });

                        return read();
                    });
                }
                return read();
            })
            .catch(err => {
                addLog('error', 'Критическая ошибка связи с сервером: ' + err.message);
            });

        function handleMessage(data) {
            if (data.type === 'log') {
                addLog(data.level, data.message);
            } else if (data.type === 'done') {
                statusText.innerText = "Импорт успешно завершен!";
                statusBadge.classList.replace('bg-blue-100', 'bg-green-100');
                statusBadge.classList.replace('text-blue-800', 'bg-green-800');
                spinner.remove();
            } else if (data.type === 'error') {
                addLog('error', 'Ошибка: ' + data.message);
                statusText.innerText = "Импорт прерван из-за ошибки.";
                statusBadge.classList.replace('bg-blue-100', 'bg-red-100');
                spinner.remove();
            }
        }

        function addLog(level, message) {
            const div = document.createElement('div');
            const colors = {
                'error': 'text-red-400',
                'warning': 'text-yellow-400',
                'success': 'text-green-400',
                'info': 'text-blue-300'
            };
            div.className = colors[level] || 'text-gray-300';
            div.innerText = `[${new Date().toLocaleTimeString()}] ${message}`;
            logContainer.appendChild(div);
            logContainer.scrollTop = logContainer.scrollHeight; // Автопрокрутка
        }
    });
</script>
@endsection