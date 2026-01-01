@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Добавить аудиокнигу</h1>

    {{-- Добавлен ID форме для перехвата в JS --}}
    <form id="upload-book-form" action="{{ route('admin.abooks.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1 font-semibold">Название:</label>
            <input type="text" name="title" required class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Автор:</label>
            <input type="text" name="author" required class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Чтец (исполнитель):</label>
            <select name="reader_id" class="w-full border p-2 rounded">
                <option value="">-- Выберите чтеца --</option>
                @foreach($readers as $reader)
                    <option value="{{ $reader->id }}">{{ $reader->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- 📚 Серия книги --}}
        <div>
            <label class="block mb-1 font-semibold">Серия:</label>
            <select name="series_id" class="w-full border p-2 rounded">
                <option value="">Без серии</option>
                @foreach(\App\Models\Series::orderBy('title')->get() as $series)
                    <option value="{{ $series->id }}">{{ $series->title }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Описание:</label>
            <textarea name="description" rows="4" class="w-full border p-2 rounded"></textarea>
        </div>

        {{-- 🔁 Выбор жанров из базы --}}
        <div>
            <label class="block mb-1 font-semibold">Жанры:</label>
            <div class="flex flex-wrap gap-4">
                @foreach($genres as $genre)
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="genres[]" value="{{ $genre->id }}" class="mr-2">
                        {{ $genre->name }}
                    </label>
                @endforeach
            </div>
            @error('genres')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Поле длительности теперь можно оставить пустым, контроллер вычислит ее сам --}}
        <div>
            <label class="block mb-1 font-semibold">Длительность (в минутах, необязательно):</label>
            <input type="number" name="duration" class="w-full border p-2 rounded" placeholder="Будет вычислено автоматически">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Обложка (jpg/png):</label>
            <input type="file" name="cover_file" accept="image/*" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Аудиофайлы глав (mp3/wav):</label>
            <input type="file" name="audio_files[]" accept="audio/mp3,audio/wav" multiple required>
        </div>

        {{-- 🟢 КОНТЕЙНЕР ПРОГРЕСС-БАРА --}}
        <div id="upload-progress-container" class="hidden border rounded-lg p-4 bg-blue-50">
            <div class="flex justify-between mb-2">
                <span id="upload-status" class="text-sm font-bold text-blue-700">Загрузка на сервер Railway...</span>
                <span id="upload-percentage" class="text-sm font-bold text-blue-700">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4">
                <div id="upload-bar" class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <p class="text-xs text-blue-600 mt-2">
                ⚠️ Пожалуйста, не закрывайте вкладку. После 100% серверу нужно время для пересылки файлов в Cloudflare R2 и обработки аудио.
            </p>
        </div>

        <div id="form-actions">
            <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600 font-bold shadow-lg">
                ➕ Добавить книгу
            </button>
        </div>
    </form>
</div>

{{-- 🛠 JAVASCRIPT ДЛЯ ПРОГРЕССА --}}
<script>
document.getElementById('upload-book-form').addEventListener('submit', function(e) {
    e.preventDefault(); // Останавливаем стандартную отправку

    const form = e.target;
    const formData = new FormData(form);
    const xhr = new XMLHttpRequest();

    // Элементы UI
    const progressContainer = document.getElementById('upload-progress-container');
    const progressBar = document.getElementById('upload-bar');
    const progressText = document.getElementById('upload-percentage');
    const statusText = document.getElementById('upload-status');
    const formActions = document.getElementById('form-actions');

    // Скрываем кнопку, показываем прогресс
    formActions.classList.add('hidden');
    progressContainer.classList.remove('hidden');

    // Отслеживание прогресса
    xhr.upload.addEventListener('progress', function(event) {
        if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            progressBar.style.width = percent + '%';
            progressText.innerText = percent + '%';
            
            if (percent === 100) {
                statusText.innerText = "Файлы на сервере. Обработка и отправка в Cloudflare R2...";
                statusText.classList.add('animate-pulse');
            }
        }
    });

    // Завершение запроса
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200 || xhr.status === 302) {
                // Успех — перенаправляем в каталог
                window.location.href = "{{ route('admin.abooks.index') }}";
            } else {
                // Ошибка
                alert('Произошла ошибка при загрузке. Проверьте размер файлов и настройки сервера.');
                formActions.classList.remove('hidden');
                progressContainer.classList.add('hidden');
            }
        }
    };

    xhr.open('POST', form.action, true);
    // Добавляем заголовок для защиты Laravel
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(formData);
});
</script>
@endsection