<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use Illuminate\Support\Collection;

interface CompanyRepositoryInterface
{
    public function createForUser(int $userId, array $data): Company;
    public function listForTelegram(int|string $chatId): Collection;

    public function findOwnedBy(int|string $chatId, int $companyId): ?Company;
    public function deleteOwnedBy(int|string $chatId, int $companyId): bool;
}
