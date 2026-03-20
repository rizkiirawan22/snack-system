<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\StockIn;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockInController extends Controller
{
    public function index(Request $request)
    {
        $query = StockIn::with(['user', 'supplier', 'items.product'])
            ->when($request->date_from,   fn($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to,     fn($q) => $q->whereDate('date', '<=', $request->date_to))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id));

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'                   => 'required|date',
            'supplier_id'            => 'nullable|exists:suppliers,id',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.purchase_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $request) {
            $stockIn = StockIn::create([
                'reference_no' => 'STK-' . date('Ymd') . '-' . str_pad(StockIn::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT),
                'user_id'      => $request->user()->id,
                'supplier_id'  => $data['supplier_id'] ?? null,
                'date'         => $data['date'],
                'notes'        => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $stockIn->items()->create($item);

                $stock = Stock::where('product_id', $item['product_id'])->lockForUpdate()->first();
                if ($stock) {
                    $before   = $stock->quantity;
                    $newAvg   = $before > 0
                        ? ($before * $stock->avg_purchase_price + $item['quantity'] * $item['purchase_price']) / ($before + $item['quantity'])
                        : $item['purchase_price'];
                    $stock->update([
                        'quantity'            => $before + $item['quantity'],
                        'avg_purchase_price'  => round($newAvg, 2),
                    ]);
                } else {
                    $before = 0;
                    $stock  = Stock::create([
                        'product_id'          => $item['product_id'],
                        'quantity'            => $item['quantity'],
                        'avg_purchase_price'  => $item['purchase_price'],
                    ]);
                }

                StockMutation::create([
                    'product_id'      => $item['product_id'],
                    'user_id'         => $request->user()->id,
                    'type'            => 'stock_in',
                    'reference_id'    => $stockIn->id,
                    'reference_type'  => 'stock_in',
                    'quantity_change' => $item['quantity'],
                    'quantity_before' => $before,
                    'quantity_after'  => $before + $item['quantity'],
                    'notes'           => "Stok masuk {$stockIn->reference_no}",
                ]);
            }

            $this->result = $stockIn->load('items.product', 'user', 'supplier');
        });

        return response()->json($this->result, 201);
    }

    public function show(StockIn $stockIn)
    {
        return response()->json($stockIn->load('items.product', 'user', 'supplier'));
    }
}
