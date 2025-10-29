<?php

namespace App\Services\Tax;

use App\Models\Company;

class TaxCalculatorService
{
    /**
     * @return array{income:float,rate:float,pay_amount:float,pay_currency:string}
     */
    public function calc(float $income, float $percent, Company $company): array
    {
        $rate = max(0.0, $percent);
        $amount = round($income * $rate / 100, 2);

        return [
            'income'       => round($income, 2),
            'rate'         => $rate,
            'pay_amount'   => $amount,
            'pay_currency' => strtoupper($company->pay_currency ?? 'EUR'),
        ];
    }
}
