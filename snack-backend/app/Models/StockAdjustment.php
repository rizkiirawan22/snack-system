<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'reference_no',
        'user_id',
        'product_id',
        'quantity_before',
        'quantity_after',
        'quantity_change',
        'reason',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
