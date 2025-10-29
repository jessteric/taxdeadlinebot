<?php

namespace App\Repositories;

use App\Models\Reminder;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use Illuminate\Support\Collection;

class ReminderRepository implements ReminderRepositoryInterface
{
    public function activeWithRelations(): Collection
    {
        return Reminder::query()
            ->active()
            ->with(['company.user'])
            ->get();
    }

    public function activeForCompany(int $companyId): Collection
    {
        return Reminder::query()
            ->active()
            ->where('company_id', $companyId)
            ->get();
    }
}
