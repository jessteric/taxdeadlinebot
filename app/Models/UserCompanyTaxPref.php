<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCompanyTaxPref extends Model
{
    protected $fillable = ['tg_user_id','company_id','last_tax_rate','last_period'];

    protected $casts = [
        'last_tax_rate' => 'decimal:3',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
