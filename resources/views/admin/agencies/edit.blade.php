@extends('layouts.app')

@section('content')
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <h2 class="text-xl font-bold mb-6">Редагувати агентство</h2>

                    <form action="{{ route('admin.agencies.update', $agency->id) }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" :value="__('Назва (Юр. особа)')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" 
                                :value="old('name', $agency->name)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="payment_details" :value="__('Платіжні реквізити')" />
                            <textarea id="payment_details" name="payment_details" rows="4" 
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            >{{ old('payment_details', $agency->payment_details) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('payment_details')" />
                        </div>
						<div>
                            <x-input-label for="royalty_percent" :value="__('Индивидуальный % Роялти (опционально)')" />
                            <x-text-input id="royalty_percent" name="royalty_percent" type="number" step="0.1" class="mt-1 block w-full" 
                                :value="old('royalty_percent', $agency->royalty_percent ?? '')" 
                                placeholder="Например: 50. Оставьте пустым для стандартной ставки." />
                            <p class="text-xs text-gray-500 mt-1">
                                Если указано, этот процент будет использоваться при расчете выплат вместо общепринятого.
                            </p>
                            <x-input-error class="mt-2" :messages="$errors->get('royalty_percent')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Зберегти зміни') }}</x-primary-button>
                            <a href="{{ route('admin.agencies.index') }}" class="text-gray-600 hover:underline">Скасувати</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection