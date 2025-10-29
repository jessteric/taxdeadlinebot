<?php

namespace App\UseCases\Telegram\Commands;

use App\Repositories\Contracts\TgUserRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\UpdateHelper;

readonly class StartCommand
{
    public function __construct(
        private TgUserRepositoryInterface $users,
        private BotApi                    $api
    ) {}

    public function handle(int|string $chatId, array $update): void
    {
        $username = UpdateHelper::username($update);
        $locale   = UpdateHelper::locale($update);

        $this->users->upsertFromTelegram($chatId, $username, $locale ?? 'en');

        $this->api->sendMessage($chatId, __('bot.welcome'));
    }
}
