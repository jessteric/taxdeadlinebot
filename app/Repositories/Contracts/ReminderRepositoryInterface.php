<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ReminderRepositoryInterface
{
    public function activeWithRelations(): Collection;
    public function activeForCompany(int $companyId): Collection;
}
