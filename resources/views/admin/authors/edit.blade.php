@extends('layouts.app')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold">Редактирование автора</h2>
                        <a href="{{ route('admin.authors.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">
                            &larr; Назад к списку
                        </a>
                    </div>

                    <form action="{{ route('admin.authors.update', $author->id) }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" :value="__('Имя автора (отображается в приложении)')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" 
                                :value="old('name', $author->name)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div class="border-t border-gray-100 my-4"></div>

                        <div class="bg-blue-50 p-4 rounded-md border border-blue-100">
                            <h3 class="text-md font-bold text-blue-800 mb-2">💰 Финансовая информация</h3>
                            <p class="text-sm text-blue-600 mb-4">
                                Заполните эти поля, если получателем роялти является не сам автор, а агентство или представитель.
                            </p>

                            <div class="mb-4">
                                <x-input-label for="agency_name" :value="__('Название Агентства / Получателя')" />
                                <x-text-input id="agency_name" name="agency_name" type="text" class="mt-1 block w-full" 
                                    :value="old('agency_name', $author->agency_name)" 
                                    placeholder="Например: AST Publishing Ltd." />
                                <p class="text-xs text-gray-500 mt-1">Оставьте пустым, если получатель — сам автор.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('agency_name')" />
                            </div>

                            <div>
                                <x-input-label for="payment_details" :value="__('Платежные реквизиты')" />
                                <textarea id="payment_details" name="payment_details" rows="3" 
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    placeholder="IBAN, PayPal, номер договора и т.д.">{{ old('payment_details', $author->payment_details) }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">Эта информация видна только администратору в отчетах.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('payment_details')" />
                            </div>
                        </div>
						<div class="mt-4">
                            <x-input-label for="royalty_percent" :value="__('Индивидуальный % Роялти')" />
                            <x-text-input id="royalty_percent" name="royalty_percent" type="number" step="0.1" class="mt-1 block w-full" 
                                :value="old('royalty_percent', $author->royalty_percent)" 
                                placeholder="Например: 35. Если пусто — берется стандарт." />
                            <x-input-error class="mt-2" :messages="$errors->get('royalty_percent')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Сохранить') }}</x-primary-button>

                            @if (session('status') === 'profile-updated')
                                <p
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-transition
                                    x-init="setTimeout(() => show = false, 2000)"
                                    class="text-sm text-gray-600"
                                >{{ __('Сохранено.') }}</p>
                            @endif
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection