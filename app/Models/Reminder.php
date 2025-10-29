<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'company_id',
        'channel',
        'address',
        'offset_days',
        'time_of_day',
        'is_active',
    ];

    protected $casts = [
        'offset_days' => 'int',
        'is_active'   => 'bool',
        'created_at'  => 'immutable_datetime',
        'updated_at'  => 'immutable_datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
