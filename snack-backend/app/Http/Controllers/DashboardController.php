<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'today_revenue'       => Sale::whereDate('date', today())->sum('total'),
            'today_transactions'  => Sale::whereDate('date', today())->count(),
            'month_revenue'       => Sale::whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('total'),
            'total_products'      => Product::where('is_active', true)->count(),
            'low_stock_count'     => Product::whereHas('stock', fn($q) => $q->whereColumn('quantity', '<=', 'products.min_stock'))->where('is_active', true)->count(),
            'recent_sales'        => Sale::with('user')->latest()->limit(5)->get(),
        ]);
    }
}
