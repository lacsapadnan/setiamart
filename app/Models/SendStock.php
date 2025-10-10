<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SendStock extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'user_id',
        'from_warehouse',
        'to_warehouse',
        'status',
        'completed_at',
        'send_stock_number'
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse');
    }

    public function sendStockDetails()
    {
        return $this->hasMany(SendStockDetail::class);
    }

    // Scope for draft records
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // Scope for completed records
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['from_warehouse', 'to_warehouse', 'status', 'completed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('stock_transfer')
            ->setDescriptionForEvent(fn(string $eventName) => "Stock transfer #{$this->id} has been {$eventName}");
    }
}
