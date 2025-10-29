<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxCalculation extends Model
{
    protected $table = 'tax_calculations';

    protected $guarded = [];

    protected $casts = [
        'period_from' => 'datetime',
        'period_to'   => 'datetime',
        'income'      => 'float',
        'rate'        => 'float',
        'pay_amount'  => 'float',
    ];

    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(TgUser::class, 'tg_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
