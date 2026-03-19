<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['user', 'items.product', 'voidedBy'])
            ->when($request->date_from, fn($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('date', '<=', $request->date_to))
            ->when($request->search,    fn($q) => $q->where('invoice_no', 'like', "%{$request->search}%"));

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'                => 'required|date',
            'discount'            => 'nullable|numeric|min:0',
            'paid'                => 'required|numeric|min:0',
            'payment_method'      => 'required|in:cash,transfer,qris',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.price'       => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $request) {
            $subtotal = collect($data['items'])->sum(fn($i) => $i['quantity'] * $i['price']);
            $discount = $data['discount'] ?? 0;
            $total    = $subtotal - $discount;
            $change   = $data['paid'] - $total;

            if ($change < 0) {
                throw new \Exception('Pembayaran kurang.');
            }

            // Cek stok semua item dulu
            foreach ($data['items'] as $item) {
                $stock = Stock::where('product_id', $item['product_id'])->first();
                if (!$stock || $stock->quantity < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi untuk produk ID {$item['product_id']}.");
                }
            }

            $sale = Sale::create([
                'invoice_no'     => 'INV-' . date('Ymd') . '-' . str_pad(Sale::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT),
                'user_id'        => $request->user()->id,
                'date'           => $data['date'],
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'total'          => $total,
                'paid'           => $data['paid'],
                'change'         => $change,
                'payment_method' => $data['payment_method'],
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'subtotal'   => $item['quantity'] * $item['price'],
                ]);

                $stock = Stock::where('product_id', $item['product_id'])->lockForUpdate()->first();
                $before = $stock->quantity;
                $stock->decrement('quantity', $item['quantity']);

                StockMutation::create([
                    'product_id'      => $item['product_id'],
                    'user_id'         => $request->user()->id,
                    'type'            => 'sale',
                    'reference_id'    => $sale->id,
                    'reference_type'  => 'sale',
                    'quantity_change' => -$item['quantity'],
                    'quantity_before' => $before,
                    'quantity_after'  => $before - $item['quantity'],
                    'notes'           => "Penjualan {$sale->invoice_no}",
                ]);
            }

            $this->result = $sale->load('items.product', 'user');
        });

        return response()->json($this->result ?? null, 201);
    }

    public function show(Sale $sale)
    {
        return response()->json($sale->load('items.product', 'user', 'voidedBy'));
    }

    public function void(Request $request, Sale $sale)
    {
        $request->validate([
            'void_reason' => 'required|string|max:255',
        ]);

        if ($sale->isVoided()) {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
        }

        DB::transaction(function () use ($request, $sale) {
            $sale->update([
                'voided_at'   => now(),
                'voided_by'   => $request->user()->id,
                'void_reason' => $request->void_reason,
            ]);

            foreach ($sale->items as $item) {
                $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->first();
                if ($stock) {
                    $before = $stock->quantity;
                    $stock->increment('quantity', $item->quantity);

                    StockMutation::create([
                        'product_id'      => $item->product_id,
                        'user_id'         => $request->user()->id,
                        'type'            => 'void',
                        'reference_id'    => $sale->id,
                        'reference_type'  => 'sale',
                        'quantity_change' => $item->quantity,
                        'quantity_before' => $before,
                        'quantity_after'  => $before + $item->quantity,
                        'notes'           => "Void {$sale->invoice_no}: {$request->void_reason}",
                    ]);
                }
            }
        });

        return response()->json($sale->fresh()->load('items.product', 'user', 'voidedBy'));
    }
}
