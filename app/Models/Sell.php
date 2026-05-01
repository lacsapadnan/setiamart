<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sell extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'cashier_id',
        'customer_id',
        'warehouse_id',
        'order_number',
        'subtotal',
        'grand_total',
        'pay',
        'change',
        'transaction_date',
        'payment_method',
        'status',
        'cash',
        'transfer',
    ];

    public function details()
    {
        return $this->hasMany(SellDetail::class);
    }

    /**
     * Line items for sales still in draft status (editable cart).
     */
    public function cartDrafts()
    {
        return $this->hasMany(SellCartDraft::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sellReturs()
    {
        return $this->hasMany(SellRetur::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('sales_transactions')
            ->setDescriptionForEvent(fn (string $eventName) => "Sale {$this->order_number} has been {$eventName}");
    }
}
