<?php

namespace App\UseCases\Telegram\Commands;

use App\Services\Telegram\BotApi;

readonly class FeaturesCommand
{
    public function __construct(private BotApi $api) {}

    public function handle(int|string $chatId): void
    {
        $txt = "Возможности:\n"
            ."• Дедлайны по отчётности — бесплатно\n"
            ."• Напоминания — бесплатно\n"
            ."• История расчётов — бесплатно (последние 5)\n"
            ."• Экспорт истории в CSV/PDF — 🔒 Pro\n"
            ."• Неограниченная история — 🔒 Pro\n"
            ."• Мультивалютность с автоконвертацией — 🔒 Pro\n"
            ."• Расширенные фильтры периодов и отчётов — 🔒 Pro\n";
        $this->api->sendMessage($chatId, $txt);
    }
}
