<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\UpdateHelper;

readonly class CompanyActionsHandler
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
        private EventRepositoryInterface $events,
        private BotApi $api
    ) {}

    /** Обработчик callback_query */
    public function handle(int|string $chatId, array $update): void
    {
        $cb = $update['callback_query'] ?? null;
        if (!$cb) return;

        $data = (string)($cb['data'] ?? '');
        if (str_starts_with($data, 'company.report:')) {
            $companyId = (int)substr($data, strlen('company.report:'));
            $this->report($chatId, $companyId);
            return;
        }

        if (str_starts_with($data, 'company.delete.confirm:')) {
            $companyId = (int)substr($data, strlen('company.delete.confirm:'));
            $this->confirmDelete($chatId, $companyId);
            return;
        }

        if (str_starts_with($data, 'company.delete:')) {
            $companyId = (int)substr($data, strlen('company.delete:'));
            $this->askDelete($chatId, $companyId);
            return;
        }
    }

    private function report(int|string $chatId, int $companyId): void
    {
        $c = $this->companies->findOwnedBy($chatId, $companyId);
        if (!$c) {
            $this->api->sendMessage($chatId, "Компания не найдена или не принадлежит вам.");
            return;
        }

        $items = $this->events->nextForCompany($c->id, 10);
        if ($items->isEmpty()) {
            $this->api->sendMessage($chatId, "Нет предстоящих дедлайнов для «{$c->name}». Запустите генератор или проверьте правила.");
            return;
        }

        $lines = $items->map(function ($e) use ($c) {
            $due = (new \DateTimeImmutable($e->due_at))
                ->setTimezone(new \DateTimeZone($c->timezone ?? 'UTC'))
                ->format('Y-m-d');
            return "• {$e->obligation->title} — <b>{$due}</b> (период {$e->period_from}–{$e->period_to}, {$c->name})";
        })->implode("\n");

        $this->api->sendMessage($chatId, "Ближайшие дедлайны:\n".$lines, ['parse_mode' => 'HTML']);
    }

    private function askDelete(int|string $chatId, int $companyId): void
    {
        $c = $this->companies->findOwnedBy($chatId, $companyId);
        if (!$c) {
            $this->api->sendMessage($chatId, "Компания не найдена или не принадлежит вам.");
            return;
        }

        $this->api->sendMessage($chatId, "Удалить «{$c->name}»? Это действие необратимо.", [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Да, удалить', 'callback_data' => "company.delete.confirm:{$c->id}"],
                        ['text' => '❌ Отмена', 'callback_data' => "company.report:{$c->id}"],
                    ],
                ],
            ],
        ]);
    }

    private function confirmDelete(int|string $chatId, int $companyId): void
    {
        $ok = $this->companies->deleteOwnedBy($chatId, $companyId);
        $this->api->sendMessage($chatId, $ok ? "Удалено." : "Не удалось удалить (не найдено).");
    }
}
