<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockIn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesSummary(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $sales = Sale::whereNull('voided_at')->whereBetween('date', [$request->date_from, $request->date_to]);

        return response()->json([
            'total_transactions' => (clone $sales)->count(),
            'total_revenue'      => (clone $sales)->sum('total'),
            'total_discount'     => (clone $sales)->sum('discount'),
            'by_payment_method'  => (clone $sales)->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(total) as total'))
                ->groupBy('payment_method')->get(),
            'daily'              => (clone $sales)->select('date', DB::raw('count(*) as transactions'), DB::raw('sum(total) as revenue'))
                ->groupBy('date')->orderBy('date')->get(),
        ]);
    }

    public function topProducts(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date',
        ]);

        $items = SaleItem::with('product.category')
            ->whereHas('sale', fn($q) => $q->whereNull('voided_at')->whereBetween('date', [$request->date_from, $request->date_to]))
            ->select('product_id', DB::raw('sum(quantity) as total_qty'), DB::raw('sum(subtotal) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        return response()->json($items);
    }

    public function stockReport()
    {
        $products = Product::with(['category', 'stock'])
            ->where('is_active', true)
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'code'       => $p->code,
                'name'       => $p->name,
                'category'   => $p->category->name,
                'weight'     => $p->weight,
                'stock'      => $p->stock?->quantity ?? 0,
                'min_stock'  => $p->min_stock,
                'is_low'     => $p->isLowStock(),
            ]);

        return response()->json($products);
    }

    public function profitReport(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        // Laba per produk: (harga jual - harga beli terakhir) x qty terjual
        $items = SaleItem::with('product.category')
            ->whereHas('sale', fn($q) => $q->whereNull('voided_at')->whereBetween('date', [$request->date_from, $request->date_to]))
            ->select(
                'product_id',
                DB::raw('sum(quantity) as total_qty'),
                DB::raw('sum(subtotal) as total_revenue'),
                DB::raw('sum(quantity * price) as sell_amount')
            )
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->get()
            ->map(function ($item) {
                $product     = $item->product;
                $buyPrice    = $product->purchase_price ?? 0;
                $costAmount  = $buyPrice * $item->total_qty;
                $profit      = $item->total_revenue - $costAmount;
                $margin      = $item->total_revenue > 0 ? round(($profit / $item->total_revenue) * 100, 1) : 0;

                return [
                    'product_id'    => $item->product_id,
                    'product_name'  => $product->name,
                    'product_code'  => $product->code,
                    'category'      => $product->category?->name,
                    'total_qty'     => $item->total_qty,
                    'total_revenue' => $item->total_revenue,
                    'total_cost'    => $costAmount,
                    'total_profit'  => $profit,
                    'margin_pct'    => $margin,
                ];
            });

        $summary = [
            'total_revenue' => $items->sum('total_revenue'),
            'total_cost'    => $items->sum('total_cost'),
            'total_profit'  => $items->sum('total_profit'),
        ];

        return response()->json(['summary' => $summary, 'products' => $items]);
    }

    public function cashierSummary(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $byUser = Sale::whereNull('voided_at')
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->select(
                'user_id',
                DB::raw('count(*) as total_transactions'),
                DB::raw('sum(total) as total_revenue'),
                DB::raw('sum(discount) as total_discount'),
                DB::raw('min(date) as first_date'),
                DB::raw('max(date) as last_date')
            )
            ->groupBy('user_id')
            ->with('user:id,name,role')
            ->get();

        return response()->json($byUser);
    }

    public function supplierReport(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $data = StockIn::with('supplier')
            ->whereBetween('date', [$request->date_from, $request->date_to])
            ->get()
            ->groupBy('supplier_id')
            ->map(function ($stockIns, $supplierId) {
                $supplier   = $stockIns->first()->supplier;
                $totalValue = $stockIns->flatMap->items->sum(fn($i) => $i->quantity * $i->purchase_price);
                $totalItems = $stockIns->flatMap->items->sum('quantity');

                return [
                    'supplier_id'      => $supplierId,
                    'supplier_name'    => $supplier?->name ?? 'Tanpa Supplier',
                    'supplier_phone'   => $supplier?->phone,
                    'total_stock_ins'  => $stockIns->count(),
                    'total_items'      => $totalItems,
                    'total_value'      => $totalValue,
                ];
            })
            ->sortByDesc('total_value')
            ->values();

        return response()->json($data);
    }
}
