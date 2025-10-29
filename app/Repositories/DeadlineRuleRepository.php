<?php

namespace App\Repositories;

use App\Models\DeadlineRule;
use App\Repositories\Contracts\DeadlineRuleRepositoryInterface;
use Illuminate\Support\Collection;

class DeadlineRuleRepository implements DeadlineRuleRepositoryInterface
{
    public function activeForCountryAndRegime(string $country, string $regime): Collection
    {
        return DeadlineRule::query()
            ->active()
            ->regime($regime)
            ->whereHas('obligation', fn($q) => $q->where('country_code', strtoupper($country))->where('is_active', true))
            ->with('obligation')
            ->get();
    }
}
