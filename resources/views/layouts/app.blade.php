{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>{{ config('app.name', 'Booka') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('logo-booka.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/alpinejs" defer></script>

    <script>
      // відновити тему до відображення
      (function () {
        try {
          if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
          }
        } catch(e) {}
      })();
    </script>
</head>

<body class="font-sans antialiased">
<div class="min-h-screen bg-gray-100 dark:bg-slate-950">

    {{-- ===================== HEADER ===================== --}}
    <header class="bg-[#0D1117] text-white py-4 shadow-md">
        <div class="mx-auto max-w-[1200px] px-4">
            <div class="flex items-center justify-between gap-4">

                {{-- ЛІВО: логотип, Головна, Адмінка, пошук та селектори --}}
                <div class="flex items-center gap-6">

                    {{-- Логотип --}}
 
                    {{-- Головна --}}
                    <a href="{{ url('/') }}"
                       class="text-sm font-medium px-3 py-1 rounded hover:text-cyan-400 transition">
                        Головна
                    </a>

                    {{-- Адмінка (для адмінів) --}}
                    @auth
                        @if(auth()->user()?->is_admin)
                            <a href="{{ route('admin.dashboard') }}"
                               class="text-sm font-medium px-3 py-1 rounded hover:text-cyan-400 transition">
                                Адмінка
                            </a>
                        @endif
                    @endauth

                    {{-- Пошук --}}
                    <form method="GET" action="{{ url('/abooks') }}" class="relative">
                        <label for="top-search" class="sr-only">Пошук</label>
                        <input id="top-search" type="text" name="search" placeholder="Пошук..."
                               value="{{ request()->query('search') }}"
                               class="rounded px-3 py-1 text-black w-48 md:w-56" />
                        <button type="submit"
                                class="absolute right-1 top-1 text-gray-600 hover:text-gray-900"
                                aria-label="Шукати">🔍</button>
                    </form>

                   {{-- Селектор жанрів --}}
                    <select name="genre" onchange="location = this.value"
                            class="rounded text-black px-2 py-1">
                        <option value="{{ url('/abooks') }}">Усі жанри</option>
                        @foreach(($allGenres ?? []) as $genre)
                            <option value="{{ url('/abooks?genre='.$genre->id) }}"
                                {{ (string) request()->query('genre') === (string) $genre->id ? 'selected' : '' }}>
                                {{ $genre->name }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Селектор авторів --}}
                    <select name="author" onchange="location = this.value"
                            class="rounded text-black px-2 py-1">
                        <option value="{{ url('/abooks') }}">Усі автори</option>
                        @foreach(($allAuthors ?? []) as $author)
                            <option value="{{ url('/abooks?author='.$author->id) }}"
                                {{ (string) request()->query('author') === (string) $author->id ? 'selected' : '' }}>
                                {{ $author->name }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Селектор виконавців --}}
                    <select name="reader" onchange="location = this.value"
                            class="rounded text-black px-2 py-1">
                        <option value="{{ url('/abooks') }}">Усі виконавці</option>
                        @foreach(($allReaders ?? []) as $reader)
                            <option value="{{ url('/abooks?reader='.$reader->id) }}"
                                {{ (string) request()->query('reader') === (string) $reader->id ? 'selected' : '' }}>
                                {{ $reader->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- ПРАВО: обране, профіль (ім'я = посилання в кабінет), вхід/вихід, тема --}}
                <div class="flex items-center gap-4 text-sm">

                    {{-- Вибране (тільки авторизованим) --}}
                    @auth
                    @endauth

                    {{-- Профіль/авторизація --}}
                    @auth
                        {{-- Ім'я користувача → посилання в кабінет --}}
                        <a href="{{ route('cabinet.index') }}"
                           class="inline-flex items-center gap-2 rounded px-2 py-1 hover:bg-white/5 hover:text-cyan-400 transition"
                           title="Перейти в кабінет">
                            @php
                                $initial = strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1));
                            @endphp
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/10 text-sm font-semibold">
                                {{ $initial }}
                            </span>
                            <span class="max-w-[160px] truncate">{{ auth()->user()->name }}</span>
                        </a>

                        {{-- Вихід --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="text-red-400 hover:text-white px-2 py-1 rounded transition">
                                Вийти
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-cyan-400">Увійти</a>
                        <a href="{{ route('register') }}" class="hover:text-cyan-400 ml-2">Реєстрація</a>
                    @endauth

                    {{-- Тема --}}
                    <button id="theme-toggle"
                            class="ml-2 px-3 py-1 border border-white/20 rounded hover:bg-gray-700"
                            title="Перемкнути тему">
                        🌙 / ☀️
                    </button>
                </div>

            </div>
        </div>
    </header>
    {{-- =================== /HEADER =================== --}}

    {{-- Заголовок сторінки (необов'язковий слот) --}}
    @isset($header)
        <header class="bg-white shadow">
            <div class="mx-auto max-w-[1200px] py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endisset

    {{-- Контент --}}
    <main class="mx-auto max-w-[1200px] px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

</div>

<script>
  // Перемикання теми з збереженням у localStorage
  document.getElementById('theme-toggle')?.addEventListener('click', () => {
    const el = document.documentElement;
    const dark = el.classList.toggle('dark');
    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch(e) {}
  });
</script>
</body>
</html>
