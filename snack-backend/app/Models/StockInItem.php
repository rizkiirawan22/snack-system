<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockInItem extends Model
{
    protected $fillable = ['stock_in_id', 'product_id', 'quantity', 'purchase_price'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockIn()
    {
        return $this->belongsTo(StockIn::class);
    }
}
