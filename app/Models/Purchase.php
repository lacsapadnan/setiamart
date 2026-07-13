<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Purchase extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'supplier_id',
        'treasury_id',
        'warehouse_id',
        'treasury_id',
        'quantity',
        'invoice',
        'order_number',
        'subtotal',
        'grand_total',
        'pay',
        'due_date',
        'reciept_date',
        'description',
        'tax',
        'status',
        'potongan',
        'payment_method',
        'cash',
        'transfer',
    ];

    protected $casts = [
        'reciept_date' => 'date',
        'due_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function treasury()
    {
        return $this->belongsTo(Treasury::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('purchase_transactions')
            ->setDescriptionForEvent(fn (string $eventName) => "Purchase {$this->order_number} has been {$eventName}");
    }
}
