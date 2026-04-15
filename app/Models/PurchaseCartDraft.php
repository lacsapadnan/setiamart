<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseCartDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'user_id',
        'product_id',
        'unit_id',
        'quantity',
        'discount_fix',
        'discount_percent',
        'price_unit',
        'total_price',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
