<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Support\Collection;

class CompanyRepository implements CompanyRepositoryInterface
{
    public function createForUser(int $userId, array $data): Company
    {
        $payload = [
            'tg_user_id'   => $userId,
            'name'         => $data['name'],
            'country_code' => $data['country_code'],
            'tax_regime'   => $data['tax_regime'],
            'timezone'     => $data['timezone'],
            'subject_type' => $data['subject_type'] ?? Company::TYPE_COMPANY,
            'person_name'  => $data['person_name'] ?? null,
            'tax_id'       => $data['tax_id'] ?? null,
        ];

        return Company::create($payload);
    }

    public function listForTelegram(int|string $chatId): Collection
    {
        $tg = TgUser::query()->byTelegramId($chatId)->first();
        if (!$tg) {
            return collect();
        }

        return Company::query()
            ->select([
                'id',
                'tg_user_id',
                'name',
                'country_code',
                'tax_regime',
                'timezone',
                'subject_type',
                'person_name',
                'tax_id',
                'created_at',
                'updated_at',
            ])
            ->where('tg_user_id', $tg->id)
            ->orderBy('subject_type')
            ->orderBy('name')
            ->get();
    }

    public function findOwnedBy(int|string $chatId, int $companyId): ?Company
    {
        $tg = TgUser::query()->byTelegramId($chatId)->first();
        if (!$tg) return null;

        return Company::query()
            ->where('id', $companyId)
            ->where('tg_user_id', $tg->id)
            ->first();
    }

    public function deleteOwnedBy(int|string $chatId, int $companyId): bool
    {
        $c = $this->findOwnedBy($chatId, $companyId);
        return (bool)$c?->delete();

    }
}
