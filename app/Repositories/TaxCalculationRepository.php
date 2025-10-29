<?php

namespace App\Repositories;

use App\Models\TaxCalculation;
use App\Repositories\Contracts\TaxCalculationRepositoryInterface;
use Illuminate\Support\Collection;

class TaxCalculationRepository implements TaxCalculationRepositoryInterface
{
    public function add(array $data): int
    {
        return TaxCalculation::query()->create($data)->id;
    }

    public function lastForUser(int $tgUserId, ?int $companyId, int $limit = 5, int $offset = 0): Collection
    {
        $q = TaxCalculation::query()
            ->with(['company'])
            ->where('tg_user_id', $tgUserId);

        if ($companyId) $q->where('company_id', $companyId);

        return $q->orderByDesc('created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function countForUser(int $tgUserId, ?int $companyId = null): int
    {
        $q = TaxCalculation::query()->where('tg_user_id', $tgUserId);
        if ($companyId) $q->where('company_id', $companyId);
        return (int)$q->count();
    }
}
