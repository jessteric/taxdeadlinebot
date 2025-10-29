<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TaxCalculationRepositoryInterface
{
    public function add(array $data): int; // returns id

    public function lastForUser(int $tgUserId, ?int $companyId, int $limit = 5, int $offset = 0): Collection;

    public function countForUser(int $tgUserId, ?int $companyId = null): int;
}
