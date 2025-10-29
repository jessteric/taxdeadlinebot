<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface DeadlineRuleRepositoryInterface
{
    public function activeForCountryAndRegime(string $country, string $regime): Collection;
}
