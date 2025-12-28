{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        $today = \Carbon\Carbon::today();
        $from  = $today->copy()->startOfMonth()->toDateString();
        $to    = $today->toDateString();
    @endphp

    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Панель адміністратора</h1>

        {{-- Панель дій --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            {{-- Керування книгами --}}
            <a href="{{ route('admin.abooks.index') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">📘 Керування книгами</h2>
                <p class="text-sm text-gray-600">Перегляд, додавання та видалення аудіокниг.</p>
            </a>

            {{-- Керування жанрами --}}
            <a href="{{ route('admin.genres.index') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">🗂 Керування жанрами</h2>
                <p class="text-sm text-gray-600">Перегляд, додавання та видалення жанрів.</p>
            </a>

            {{-- Керування читцями --}}
            <a href="{{ route('admin.readers.index') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">🎙️ Керування читцями</h2>
                <p class="text-sm text-gray-600">Перегляд, додавання та видалення читців.</p>
            </a>

            {{-- Керування серіями --}}
            <a href="{{ route('admin.series.index') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">📚 Керування серіями</h2>
                <p class="text-sm text-gray-600">Перегляд, додавання та видалення серій книг.</p>
            </a>

            {{-- Надсилання сповіщень (push) --}}
            <a href="{{ route('admin.push.create') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">📣 Надсилання сповіщень</h2>
                <p class="text-sm text-gray-600">Створення та надсилання сповіщень усім користувачам.</p>
            </a>

            {{-- Статистика прослуховувань: з початку місяця до сьогодні, групування по днях --}}
            <a href="{{ route('admin.listens.stats', ['from' => $from, 'to' => $to, 'group' => 'day']) }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">📊 Статистика прослуховувань</h2>
                <p class="text-sm text-gray-600">
                    Агреговані інтервали, список книг за період, експорти та деталізація по книгах.
                </p>
            </a>

            {{-- 💰 Роялти --}}
            <a href="{{ route('admin.royalties.index') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition">
                <h2 class="text-xl font-semibold mb-2">💰 Роялти</h2>
                <p class="text-sm text-gray-600">Финансовый отчет и расчет выплат авторам.</p>
            </a>
			{{-- 👇 АГЕНТСТВ 👇 --}}
            <a href="{{ route('admin.agencies.index') }}"
               class="block p-6 border rounded hover:bg-gray-100 transition shadow-sm border-l-4 border-purple-500 bg-purple-50">
                <h2 class="text-xl font-bold text-purple-900 mb-2">🏢 Агентства</h2>
                <p class="text-sm text-purple-800">Правообладатели, реквизиты и эксклюзивные ставки.</p>
            </a>

        </div>
    </div>
@endsection