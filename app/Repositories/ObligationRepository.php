<?php

namespace App\Repositories;

use App\Models\Obligation;
use App\Repositories\Contracts\ObligationRepositoryInterface;
use Illuminate\Support\Collection;

class ObligationRepository implements ObligationRepositoryInterface
{
    public function activeByCountry(string $country): Collection
    {
        return Obligation::query()->active()->country($country)->get();
    }
}
