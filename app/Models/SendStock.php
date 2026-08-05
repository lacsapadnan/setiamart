<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SendStock extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'from_warehouse',
        'to_warehouse',
        'status',
        'completed_at',
        'send_stock_number',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SendStock $sendStock) {
            if (empty($sendStock->send_stock_number)) {
                $sendStock->send_stock_number = static::generateSendStockNumber();
            }
        });
    }

    /**
     * Generate a unique send stock number for today.
     *
     * Uses the highest existing daily sequence (not total row count) so deleted
     * rows and concurrent creates cannot reuse a number like PS-YYYYMMDD-11618.
     */
    public static function generateSendStockNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "PS-{$date}-";

        return Cache::lock("send-stock-number:{$date}", 10)->block(5, function () use ($prefix) {
            $maxSequence = (int) static::query()
                ->where('send_stock_number', 'like', $prefix.'%')
                ->selectRaw(
                    'MAX(CAST(SUBSTRING(send_stock_number, ?) AS UNSIGNED)) as max_seq',
                    [strlen($prefix) + 1]
                )
                ->value('max_seq');

            $sequence = $maxSequence + 1;

            do {
                $candidate = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                $sequence++;
            } while (static::where('send_stock_number', $candidate)->exists());

            return $candidate;
        });
    }

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
            ->setDescriptionForEvent(fn (string $eventName) => "Stock transfer #{$this->id} has been {$eventName}");
    }
}
