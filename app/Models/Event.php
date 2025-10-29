<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = [
        'company_id',
        'obligation_id',
        'period_from',
        'period_to',
        'due_at',
        'status',
        'meta',
    ];

    protected $casts = [
        'period_from' => 'immutable_date',
        'period_to'   => 'immutable_date',
        'due_at'      => 'immutable_datetime',
        'meta'        => 'array',
        'created_at'  => 'immutable_datetime',
        'updated_at'  => 'immutable_datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    public function scopeUpcoming($q)
    {
        return $q->whereIn('status', ['upcoming','sent']);
    }

    public function scopeDueBetween($q, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        return $q->whereBetween('due_at', [$from, $to]);
    }
}
