<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obligation extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'country_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(DeadlineRule::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeCountry($q, string $country)
    {
        return $q->where('country_code', strtoupper($country));
    }
}
