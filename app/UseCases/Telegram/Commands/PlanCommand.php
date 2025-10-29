<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\TaxCalculationRepositoryInterface;
use App\Services\Telegram\BotApi;

readonly class PlanCommand
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
        private TaxCalculationRepositoryInterface $calcRepo,
        private BotApi $api
    ) {}

    public function handle(int|string $chatId): void
    {
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!$u) { $this->api->sendMessage($chatId, "Введите /start."); return; }

        $companies = $this->companies->listForTelegram($chatId);
        $calcCount = $this->calcRepo->countForUser($u->id, null);

        // Пока биллинг отложен — жёстко считаем Free
        $txt = "Текущий план: Free\n\n"
            ."Лимиты:\n"
            ."• История расчётов: 5 последних записей\n"
            ."• Экспорт CSV/PDF: 🔒 Pro \n"
            ."• Расширенные напоминания и фильтры: 🔒 Pro\n\n"
            ."Статистика:\n"
            ."• Компаний: {$companies->count()}\n"
            ."• История расчётов всего: {$calcCount}\n\n"
            ."Для апгрейда напишите /features — покажем витрину.";
        $this->api->sendMessage($chatId, $txt);
    }
}
