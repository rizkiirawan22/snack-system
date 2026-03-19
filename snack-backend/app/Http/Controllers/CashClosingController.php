<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashClosingController extends Controller
{
    public function index(Request $request)
    {
        $query = CashClosing::with('user')
            ->when($request->date_from, fn($q) => $q->whereDate('closing_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('closing_date', '<=', $request->date_to));

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Ambil data penjualan hari ini untuk preview sebelum closing.
     */
    public function preview(Request $request)
    {
        $date = $request->date ?? today()->toDateString();

        $sales = Sale::whereNull('voided_at')->whereDate('date', $date);

        $byMethod = (clone $sales)
            ->select('payment_method', DB::raw('sum(total) as total'), DB::raw('count(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        return response()->json([
            'date'               => $date,
            'total_transactions' => (clone $sales)->count(),
            'total_revenue'      => (clone $sales)->sum('total'),
            'cash_sales'         => $byMethod['cash']->total     ?? 0,
            'transfer_sales'     => $byMethod['transfer']->total ?? 0,
            'qris_sales'         => $byMethod['qris']->total     ?? 0,
            'has_closing'        => CashClosing::whereDate('closing_date', $date)->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'closing_date'    => 'required|date',
            'opening_balance' => 'required|numeric|min:0',
            'actual_cash'     => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        $date  = $data['closing_date'];
        $sales = Sale::whereNull('voided_at')->whereDate('date', $date);

        $byMethod = (clone $sales)
            ->select('payment_method', DB::raw('sum(total) as total'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $cashSales    = $byMethod['cash']->total     ?? 0;
        $transferSales = $byMethod['transfer']->total ?? 0;
        $qrisSales    = $byMethod['qris']->total     ?? 0;
        $totalRevenue = (clone $sales)->sum('total');
        $totalTrx     = (clone $sales)->count();
        $expected     = $data['opening_balance'] + $cashSales;
        $difference   = $data['actual_cash'] - $expected;

        $prefix = 'CLO-' . date('Ymd', strtotime($date)) . '-';
        $count  = CashClosing::where('reference_no', 'like', $prefix . '%')->count() + 1;

        $closing = CashClosing::create([
            'reference_no'      => $prefix . str_pad($count, 4, '0', STR_PAD_LEFT),
            'user_id'           => $request->user()->id,
            'closing_date'      => $date,
            'opening_balance'   => $data['opening_balance'],
            'cash_sales'        => $cashSales,
            'transfer_sales'    => $transferSales,
            'qris_sales'        => $qrisSales,
            'total_expected'    => $expected,
            'actual_cash'       => $data['actual_cash'],
            'difference'        => $difference,
            'total_transactions' => $totalTrx,
            'total_revenue'     => $totalRevenue,
            'notes'             => $data['notes'] ?? null,
        ]);

        return response()->json($closing->load('user'), 201);
    }

    public function show(CashClosing $cashClosing)
    {
        return response()->json($cashClosing->load('user'));
    }
}
