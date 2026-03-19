<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no',
        'user_id',
        'date',
        'subtotal',
        'discount',
        'total',
        'paid',
        'change',
        'payment_method',
        'notes',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'date'      => 'date',
        'subtotal'  => 'decimal:2',
        'discount'  => 'decimal:2',
        'total'     => 'decimal:2',
        'paid'      => 'decimal:2',
        'change'    => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
