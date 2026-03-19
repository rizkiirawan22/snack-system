<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    protected $fillable = [
        'reference_no',
        'user_id',
        'closing_date',
        'opening_balance',
        'cash_sales',
        'transfer_sales',
        'qris_sales',
        'total_expected',
        'actual_cash',
        'difference',
        'total_transactions',
        'total_revenue',
        'notes',
    ];

    protected $casts = [
        'closing_date'      => 'date',
        'opening_balance'   => 'decimal:2',
        'cash_sales'        => 'decimal:2',
        'transfer_sales'    => 'decimal:2',
        'qris_sales'        => 'decimal:2',
        'total_expected'    => 'decimal:2',
        'actual_cash'       => 'decimal:2',
        'difference'        => 'decimal:2',
        'total_revenue'     => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
