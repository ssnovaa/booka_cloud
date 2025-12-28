<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\Request;

class AuthorController extends Controller
{
    public function index()
    {
        // Сортировка по имени + пагинация
        $authors = Author::withCount('books')->orderBy('name')->paginate(20);
        return view('admin.authors.index', compact('authors'));
    }

    public function edit(Author $author)
    {
        return view('admin.authors.edit', compact('author'));
    }

    public function update(Request $request, Author $author)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // Старые поля (можно оставить для совместимости)
            'agency_name' => 'nullable|string|max:255',
            'payment_details' => 'nullable|string',
            
            // ⬅️ НОВОЕ ПОЛЕ: Индивидуальный процент роялти
            'royalty_percent' => 'nullable|numeric|min:0|max:100', 
        ]);

        $author->update($data);

        return redirect()->route('admin.authors.index')->with('success', 'Автор обновлен');
    }
}