@extends('layouts.app')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold">🏢 Агентства та Правовласники</h2>
                        <a href="{{ route('admin.agencies.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            + Додати агентство
                        </a>
                    </div>

                    @if(session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Назва</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Реквізити</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Книг</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Дії</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($agencies as $agency)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                            {{ $agency->name }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $agency->payment_details }}">
                                            {{ $agency->payment_details ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs">
                                                {{ $agency->books_count }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('admin.agencies.edit', $agency->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-4">Редагувати</a>
                                            
                                            <form action="{{ route('admin.agencies.destroy', $agency->id) }}" method="POST" class="inline" onsubmit="return confirm('Видалити агентство? Усі книги цього агентства залишаться, але поле буде очищено.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Видалити</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                            Список порожній. Додайте першого правовласника.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection