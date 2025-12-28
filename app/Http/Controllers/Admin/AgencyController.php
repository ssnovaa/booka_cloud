<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    public function index()
    {
        $agencies = Agency::withCount('books')->orderBy('name')->get();
        return view('admin.agencies.index', compact('agencies'));
    }

    public function create()
    {
        return view('admin.agencies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'payment_details' => 'nullable|string',
            'royalty_percent' => 'nullable|numeric|min:0|max:100', // ⬅️ Валидация
        ]);
        
        Agency::create($data);
        
        return redirect()->route('admin.agencies.index')->with('success', 'Агентство создано');
    }

    public function edit(Agency $agency)
    {
        return view('admin.agencies.edit', compact('agency'));
    }

    public function update(Request $request, Agency $agency)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'payment_details' => 'nullable|string',
            'royalty_percent' => 'nullable|numeric|min:0|max:100', // ⬅️ Валидация
        ]);

        $agency->update($data);

        return redirect()->route('admin.agencies.index')->with('success', 'Обновлено');
    }

    public function destroy(Agency $agency)
    {
        $agency->delete();
        return back()->with('success', 'Удалено');
    }
}