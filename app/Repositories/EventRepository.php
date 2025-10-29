<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\Event;
use App\Models\TgUser;
use App\Repositories\Contracts\EventRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EventRepository implements EventRepositoryInterface
{
    private function baseQueryForUser(int|string $chatId, ?int $companyId = null)
    {
        $user = TgUser::query()->byTelegramId($chatId)->first();
        if (!$user) {
            return Event::query()->whereRaw('1=0');
        }

        $companyIds = Company::query()
            ->where('tg_user_id', $user->id)
            ->when($companyId, fn($q) => $q->where('id', $companyId))
            ->pluck('id');

        $fromDate = CarbonImmutable::today()->subDays(30);

        return Event::query()
            ->with(['company', 'obligation'])
            ->whereIn('company_id', $companyIds)
            ->where('due_at', '>=', $fromDate->startOfDay())
            ->orderBy('due_at');
    }

    public function nextForUserChat(int|string $chatId, int $limit = 5, int $offset = 0, ?int $companyId = null): Collection
    {
        return $this->baseQueryForUser($chatId, $companyId)
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    public function countUpcomingForUserChat(int|string $chatId, ?int $companyId = null): int
    {
        return (int) $this->baseQueryForUser($chatId, $companyId)->count();
    }

    public function upsertEvent(array $data): void
    {
        $model = Event::updateOrCreate(
            [
                'company_id'    => $data['company_id'],
                'obligation_id' => $data['obligation_id'],
                'period_from'   => $data['period_from'],
                'period_to'     => $data['period_to'],
            ],
            [
                'due_at' => $data['due_at'],
                'status' => $data['status'] ?? 'upcoming',
                'meta'   => $data['meta'] ?? null,
            ]
        );
    }

    public function upcomingForCompanyAtOffset(
        int $companyId,
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): Collection {
        return Event::query()
            ->where('company_id', $companyId)
            ->upcoming()
            ->dueBetween($from, $to)
            ->with(['obligation','company'])
            ->get();
    }

    public function markSent(int $eventId): void
    {
        Event::whereKey($eventId)->update(['status' => 'sent']);
    }

    public function markDone(int $eventId): void
    {
        Event::whereKey($eventId)->update(['status' => 'done']);
    }

    public function find(int $id): ?Event
    {
        return Event::with(['obligation','company'])->find($id);
    }

    public function nextForCompany(int $companyId, int $limit = 10): Collection
    {
        $now = CarbonImmutable::now();
        return Event::query()
            ->where('company_id', $companyId)
            ->where('due_at', '>=', $now->startOfDay()->toDateTimeString())
            ->orderBy('due_at')
            ->limit($limit)
            ->get();
    }
}
