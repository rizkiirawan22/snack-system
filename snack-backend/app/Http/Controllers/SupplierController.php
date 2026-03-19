<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount('stockIns')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', $request->is_active));

        // Kalau request all=1, kembalikan semua tanpa paginasi (untuk dropdown)
        if ($request->all) {
            return response()->json($query->where('is_active', true)->orderBy('name')->get());
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:100',
            'address'   => 'nullable|string',
            'notes'     => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        return response()->json(Supplier::create($data), 201);
    }

    public function show(Supplier $supplier)
    {
        $supplier->load(['stockIns' => fn($q) => $q->with('items.product')->latest()->limit(10)]);
        $supplier->loadCount('stockIns');
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:100',
            'address'   => 'nullable|string',
            'notes'     => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier->update($data);
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(null, 204);
    }
}
