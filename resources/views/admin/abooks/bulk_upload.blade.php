<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            РЕЖИМ ОТЛАДКИ
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- КРАСНЫЙ БЛОК ДЛЯ ПРОВЕРКИ --}}
            <div style="background-color: red; color: white; padding: 20px; font-size: 16px; font-weight: bold;">
                <h1>ЕСЛИ ВЫ ЭТО ВИДИТЕ — ШАБЛОН РАБОТАЕТ!</h1>
                <hr style="margin: 10px 0; border-color: white;">
                <p>Количество книг: {{ count($importList) }}</p>
                <pre style="background: black; color: #0f0; padding: 10px; margin-top: 10px;">
                    {{ print_r($importList, true) }}
                </pre>
            </div>
        </div>
    </div>
</x-app-layout>