<?php

namespace App\Services\Telegram;

use App\Console\Commands\AddCompanyCommand;
use App\UseCases\Telegram\Commands\NextCommand;
use App\UseCases\Telegram\Commands\StartCommand;

readonly class BotRouter
{
    public function __construct(
        private StartCommand      $start,
        private AddCompanyCommand $addCompany,
        private NextCommand       $next,
        private BotApi            $api
    ) {}

    public function dispatch(array $update): void
    {
        $chatId = UpdateHelper::chatId($update);
        if ($chatId === null) return;

        $lc = UpdateHelper::locale($update);
        app()->setLocale($lc === 'ru' ? 'ru' : 'en');

        $text = UpdateHelper::text($update);

        // 1) continue wizard if any
        $state = new ConversationState($chatId);
        if ($state->step() && !$this->isSlashCommand($text)) {
            if ($this->addCompany->continueFlow($chatId, $update)) {
                return;
            }
        }

        // 2) commands
        if (str_starts_with($text, '/start'))      { $this->start->handle($chatId, $update); return; }
        if (str_starts_with($text, '/addcompany')) { $this->addCompany->startFlow($chatId); return; }
        if (str_starts_with($text, '/next'))       { $this->next->handle($chatId); return; }

        // 3) default
        $this->api->sendMessage($chatId, "Unknown command. Try /addcompany or /next");
    }

    private function isSlashCommand(?string $text): bool
    {
        return is_string($text) && str_starts_with($text, '/');
    }
}
