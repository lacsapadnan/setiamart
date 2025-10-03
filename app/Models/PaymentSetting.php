<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PaymentSetting extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'in_town_account_number',
        'in_town_account_name',
        'out_of_town_account_number',
        'out_of_town_account_name',
    ];

    /**
     * Get the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'in_town_account_number',
                'in_town_account_name',
                'out_of_town_account_number',
                'out_of_town_account_name',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
