<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeadlineRule extends Model
{
    protected $fillable = [
        'obligation_id',
        'regime',
        'rrule_json',
        'due_day',
        'due_shift',
        'grace_days',
        'holiday_calendar_code',
        'is_active',
    ];

    protected $casts = [
        'rrule_json' => 'array',
        'due_day' => 'int',
        'grace_days' => 'int',
        'is_active' => 'bool',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeRegime($q, string $regime)
    {
        return $q->where('regime', $regime);
    }
}
