<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'unit',
        'weight',
        'purchase_price',
        'selling_price',
        'min_stock',
        'description',
        'image',
        'is_active',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price'  => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    public function stockInItems()
    {
        return $this->hasMany(StockInItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? url(Storage::url($this->image)) : null;
    }

    public function isLowStock(): bool
    {
        return ($this->stock?->quantity ?? 0) <= $this->min_stock;
    }
}
