<?php

namespace App\Services\Telegram;

use App\Models\TgUser;
use App\Services\Billing\Features;

final class CommandListRenderer
{
    public function renderFor(?TgUser $user): string
    {
        $plan   = Features::userPlan($user);
        $export = Features::exportEnabled($plan);

        $title  = __('commands.title');
        $items  = __('commands.items'); // array

        // Базовый перечень:
        $keys = [
            'addcompany','companies','next','tax','tax_history',
            'setcurrency','setrate_default','reminders','plan','features',
        ];

        // Добавляем /export только если доступен по плану
        if ($export) {
            $keys[] = 'export';
        }

        $lines = [ __('commands.hello'), '', $title ];
        foreach ($keys as $k) {
            if (!empty($items[$k])) {
                $lines[] = $items[$k];
            }
        }
        return implode("\n", $lines);
    }
}
