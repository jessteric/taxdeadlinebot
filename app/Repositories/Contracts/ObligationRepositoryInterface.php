<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ObligationRepositoryInterface
{
    public function activeByCountry(string $country): Collection;
}
