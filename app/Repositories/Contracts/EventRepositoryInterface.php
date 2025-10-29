<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Support\Collection;

interface EventRepositoryInterface
{
    public function nextForUserChat(
        int|string $chatId,
        int $limit = 5,
        int $offset = 0,
        ?int $companyId = null
    ): Collection;

    public function upsertEvent(array $data): void;

    public function upcomingForCompanyAtOffset(
        int $companyId,
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): Collection;

    public function markSent(int $eventId): void;

    public function markDone(int $eventId): void;

    public function find(int $id): ?Event;

    public function nextForCompany(int $companyId, int $limit = 10): Collection;

    public function countUpcomingForUserChat(
        int|string $chatId,
        ?int $companyId = null
    ): int;
}
