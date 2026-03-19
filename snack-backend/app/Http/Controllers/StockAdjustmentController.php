<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StockAdjustment::with(['user', 'product.category'])
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id));

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'quantity_after' => 'required|integer|min:0',
            'reason'        => 'required|in:shrinkage,damage,count,other',
            'notes'         => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data, $request) {
            $stock = Stock::where('product_id', $data['product_id'])->lockForUpdate()->first();
            $before = $stock ? $stock->quantity : 0;
            $after  = $data['quantity_after'];
            $change = $after - $before;

            $prefix = 'ADJ-' . date('Ymd') . '-';
            $count  = StockAdjustment::where('reference_no', 'like', $prefix . '%')->count() + 1;

            $adjustment = StockAdjustment::create([
                'reference_no'   => $prefix . str_pad($count, 4, '0', STR_PAD_LEFT),
                'user_id'        => $request->user()->id,
                'product_id'     => $data['product_id'],
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'quantity_change' => $change,
                'reason'         => $data['reason'],
                'notes'          => $data['notes'] ?? null,
            ]);

            if ($stock) {
                $stock->update(['quantity' => $after]);
            } else {
                Stock::create(['product_id' => $data['product_id'], 'quantity' => $after]);
            }

            StockMutation::create([
                'product_id'      => $data['product_id'],
                'user_id'         => $request->user()->id,
                'type'            => 'adjustment',
                'reference_id'    => $adjustment->id,
                'reference_type'  => 'adjustment',
                'quantity_change' => $change,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'notes'           => "Penyesuaian stok ({$data['reason']}): {$adjustment->reference_no}",
            ]);

            $this->result = $adjustment->load('user', 'product.category');
        });

        return response()->json($this->result ?? null, 201);
    }

    public function mutations(Request $request, int $productId)
    {
        $mutations = StockMutation::with('user')
            ->where('product_id', $productId)
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(20);

        return response()->json($mutations);
    }
}
