<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCalculation extends Model
{
    protected $fillable = [
        'tg_user_id','company_id',
        'period_from','period_to','period_label',
        'income','rate','pay_amount','pay_currency',
    ];

    protected $casts = [
        'period_from' => 'immutable_date',
        'period_to'   => 'immutable_date',
        'income'      => 'decimal:2',
        'rate'        => 'decimal:3',
        'pay_amount'  => 'decimal:2',
        'created_at'  => 'immutable_datetime',
        'updated_at'  => 'immutable_datetime',
    ];
}
