@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Массовая загрузка книг с вашего компьютера</h1>

    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
        <p class="text-blue-700">
            <strong>Инструкция:</strong> Выберите папку на вашем ПК. Каждая подпапка должна называться в формате <code>Название_Автор</code> и содержать файлы (обложку и mp3).
        </p>
    </div>

    <div class="space-y-6">
        {{-- Выбор общих параметров для всех книг в этой сессии --}}
        <div class="grid grid-cols-2 gap-4 bg-white p-4 border rounded shadow-sm">
            <div>
                <label class="block font-semibold mb-1">Общие жанры:</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($genres as $genre)
                        <label class="text-sm"><input type="checkbox" class="genre-checkbox" value="{{ $genre->id }}"> {{ $genre->name }}</label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="block font-semibold mb-1">Общий чтец (если один):</label>
                <select id="common-reader" class="w-full border p-2 rounded text-sm">
                    <option value="">-- Выберите чтеца --</option>
                    @foreach($readers as $reader)
                        <option value="{{ $reader->id }}">{{ $reader->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Кнопка выбора папки --}}
        <div class="flex items-center gap-4">
            <input type="file" id="folder-input" webkitdirectory directory multiple class="hidden">
            <button onclick="document.getElementById('folder-input').click()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-bold shadow-md">
                📁 Выбрать папку с книгами на ПК
            </button>
            <span id="files-count" class="text-gray-600">Папка не выбрана</span>
        </div>

        {{-- Очередь загрузки --}}
        <div id="upload-queue" class="hidden space-y-3">
            <h2 class="text-lg font-semibold border-b pb-2">Очередь загрузки</h2>
            <div id="queue-items" class="space-y-2 max-h-[50vh] overflow-y-auto pr-2">
                {{-- Сюда JS добавит книги --}}
            </div>
            <button id="start-upload-btn" class="w-full bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 shadow-lg">
                🚀 Начать загрузку всех книг в R2
            </button>
        </div>
    </div>
</div>

<script>
    let bookQueue = [];

    document.getElementById('folder-input').addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        const booksMap = {};

        // Группируем файлы по папкам
        files.forEach(file => {
            const pathParts = file.webkitRelativePath.split('/');
            if (pathParts.length < 2) return;
            
            const folderName = pathParts[pathParts.length - 2];
            if (!booksMap[folderName]) booksMap[folderName] = { audio: [], cover: null };

            if (file.type.startsWith('audio/')) {
                booksMap[folderName].audio.push(file);
            } else if (file.type.startsWith('image/')) {
                booksMap[folderName].cover = file;
            }
        });

        // Создаем очередь
        bookQueue = Object.keys(booksMap).map(folder => {
            const parts = folder.split('_');
            return {
                folder: folder,
                title: parts[0]?.replace(/_/g, ' ') || folder,
                author: parts[1]?.replace(/_/g, ' ') || 'Неизвестен',
                cover: booksMap[folder].cover,
                audio: booksMap[folder].audio
            };
        });

        renderQueue();
    });

    function renderQueue() {
        const container = document.getElementById('queue-items');
        container.innerHTML = '';
        document.getElementById('upload-queue').classList.remove('hidden');
        document.getElementById('files-count').innerText = `Найдено книг: ${bookQueue.length}`;

        bookQueue.forEach((book, index) => {
            container.innerHTML += `
                <div id="book-row-${index}" class="flex items-center justify-between p-3 bg-white border rounded shadow-sm">
                    <div class="flex-1">
                        <div class="font-bold">${book.title}</div>
                        <div class="text-xs text-gray-500">${book.author} | ${book.audio.length} файлов | ${book.cover ? '✅ Обложка есть' : '❌ Нет обложки'}</div>
                    </div>
                    <div class="w-1/3 bg-gray-200 rounded-full h-2 mx-4 overflow-hidden">
                        <div id="progress-bar-${index}" class="bg-blue-500 h-full w-0 transition-all duration-300"></div>
                    </div>
                    <div id="status-${index}" class="text-sm font-semibold text-gray-400">Ожидание</div>
                </div>
            `;
        });
    }

    document.getElementById('start-upload-btn').addEventListener('click', async function() {
        this.disabled = true;
        this.innerText = "Загрузка в процессе...";

        const genres = Array.from(document.querySelectorAll('.genre-checkbox:checked')).map(cb => cb.value);
        const readerId = document.getElementById('common-reader').value;

        for (let i = 0; i < bookQueue.length; i++) {
            const book = bookQueue[i];
            const statusLabel = document.getElementById(`status-${i}`);
            const progressBar = document.getElementById(`progress-bar-${i}`);
            
            statusLabel.innerText = "Загрузка...";
            statusLabel.className = "text-sm font-semibold text-blue-600";

            try {
                const formData = new FormData();
                formData.append('title', book.title);
                formData.append('author', book.author);
                formData.append('reader_id', readerId);
                genres.forEach(g => formData.append('genres[]', g));
                if (book.cover) formData.append('cover_file', book.cover);
                book.audio.forEach(f => formData.append('audio_files[]', f));

                await uploadBook(formData, percent => {
                    progressBar.style.width = percent + '%';
                });

                statusLabel.innerText = "✅ Готово";
                statusLabel.className = "text-sm font-semibold text-green-600";
            } catch (err) {
                statusLabel.innerText = "❌ Ошибка";
                statusLabel.className = "text-sm font-semibold text-red-600";
            }
        }

        this.innerText = "Все задачи завершены";
    });

    function uploadBook(formData, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', "{{ route('admin.abooks.store') }}", true);
            xhr.setRequestHeader('X-CSRF-TOKEN', "{{ csrf_token() }}");
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = e => {
                if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
            };

            xhr.onload = () => (xhr.status === 200 || xhr.status === 302) ? resolve() : reject();
            xhr.onerror = () => reject();
            xhr.send(formData);
        });
    }
</script>
@endsection