@extends('layouts.app')

@section('content')
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Добавить чтеца</h1>

    <form action="{{ route('admin.readers.store') }}" method="POST" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1 font-semibold" for="name">Имя чтеца:</label>
            <input 
                type="text" 
                id="name"
                name="name" 
                required 
                class="w-full border p-2 rounded" 
                value="{{ old('name') }}"
                placeholder="Введите имя чтеца"
            >
            @error('name')
                <p class="text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Добавить
        </button>
    </form>
</div>
@endsection
