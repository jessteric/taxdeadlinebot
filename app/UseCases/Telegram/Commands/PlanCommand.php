<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\TgUser;
use App\Services\Billing\Features;
use App\Services\Telegram\BotApi;

final class PlanCommand
{
    public function __construct(private BotApi $api) {}

    /**
     * /plan — показать текущий план и возможности
     */
    public function handle(int|string $chatId): void
    {
        $user = TgUser::query()->byTelegramId($chatId)->first();
        $plan = Features::userPlan($user);              // 'free' | 'starter' | 'pro' | 'business'
        $planTitle = $this->humanPlan($plan);           // красивое имя для пользователя (Free/Starter/Pro/Business)

        $lines = [];
        $lines[] = __('plan.current_plan') . ': ' . $planTitle;
        $lines[] = '';
        $lines[] = __('plan.capabilities') . ':';
        $lines[] = '• ' . __('plan.history') . ': ' . Features::historyLimitLabel($plan);
        $lines[] = '• ' . __('plan.export_any') . ': ' . (Features::exportEnabled($plan) ? __('plan.on') : __('plan.off') . ' PRO');
        $lines[] = '• ' . __('plan.fx') . ': ' . (Features::fxEnabled($plan) ? __('plan.on') : __('plan.off') . ' PRO');
        // Можно дописать командный доступ, если понадобится:
        // $lines[] = '• ' . __('plan.team') . ': ' . (Features::teamEnabled($plan) ? __('plan.on') : __('plan.off') . ' Business');

        $kb = [
            [
                ['text' => __('plan.more_details'), 'callback_data' => 'plan:details'],
            ],
            [
                ['text' => __('plan.upgrade'), 'callback_data' => 'plan:upgrade_menu'],
            ],
        ];

        $this->api->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $kb],
        ]);
    }

    /**
     * Обрабатываем коллбеки plan:*
     */
    public function handleCallback(int|string $chatId, array $update): void
    {
        $data = (string)($update['callback_query']['data'] ?? '');
        if (!str_starts_with($data, 'plan:')) {
            return;
        }

        $action = substr($data, strlen('plan:'));

        switch (true) {
            case $action === 'details':
                $this->showDetails($chatId);
                return;

            case $action === 'upgrade_menu':
                $this->showUpgradeMenu($chatId);
                return;

            // Выбор покупки с биллингом: plan:buy:<plan>:<billing>
            // <plan> = starter|pro|business
            // <billing> = monthly|yearly
            case str_starts_with($action, 'buy:'):
                $parts = explode(':', $action); // ['buy', '<plan>', '<billing>']
                $plan   = $parts[1] ?? '';
                $billing= $parts[2] ?? '';
                $this->confirmSelection($chatId, $plan, $billing);
                return;

            default:
                // нераспознанное — молча игнорим
                return;
        }
    }

    // ===== Вспомогательные методы =====

    private function showDetails(int|string $chatId): void
    {
        // Краткая витрина тарифов с годовым -15%
        // Тексты — из lang-файлов, чтобы было RU/EN
        $msg = implode("\n\n", [
            // Free
            __('plan.plan_free') . "\n"
            . '• ' . __('plan.history') . ': ' . __('plan.history_free') . "\n"
            . '• ' . __('plan.export_any') . ': ' . __('plan.off') . "\n"
            . '• ' . __('plan.fx') . ': ' . __('plan.off'),

            // Starter
            __('plan.plan_starter') . "\n"
            . '• ' . __('plan.history') . ': ' . __('plan.history_starter') . "\n"
            . '• ' . __('plan.export_csv') . ': ' . __('plan.on') . "\n"
            . '• ' . __('plan.export_pdf') . ': ' . __('plan.off') . "\n"
            . '• ' . __('plan.fx') . ': ' . __('plan.off'),

            // Pro
            __('plan.plan_pro') . "\n"
            . '• ' . __('plan.history') . ': ' . __('plan.history_unlim') . "\n"
            . '• ' . __('plan.export_csv') . ': ' . __('plan.on') . "\n"
            . '• ' . __('plan.export_pdf') . ': ' . __('plan.on') . "\n"
            . '• ' . __('plan.fx') . ': ' . __('plan.on'),

            // Business
            __('plan.plan_business') . "\n"
            . '• ' . __('plan.history') . ': ' . __('plan.history_unlim') . "\n"
            . '• ' . __('plan.export_csv') . ': ' . __('plan.on') . "\n"
            . '• ' . __('plan.export_pdf') . ': ' . __('plan.on') . "\n"
            . '• ' . __('plan.fx') . ': ' . __('plan.on') . "\n"
            . '• ' . __('plan.team') . ': ' . __('plan.on'),
        ]);

        $this->api->sendMessage($chatId, $msg);
    }

    private function showUpgradeMenu(int|string $chatId): void
    {
        // Дадим сразу кнопки: Starter/Pro/Business × Monthly/Yearly(-15%)
        $kb = [
            [
                ['text' => 'Starter — ' . __('plan.per_month'), 'callback_data' => 'plan:buy:starter:monthly'],
                ['text' => 'Starter — ' . __('plan.per_year'),  'callback_data' => 'plan:buy:starter:yearly'],
            ],
            [
                ['text' => 'Pro — ' . __('plan.per_month'),      'callback_data' => 'plan:buy:pro:monthly'],
                ['text' => 'Pro — ' . __('plan.per_year'),       'callback_data' => 'plan:buy:pro:yearly'],
            ],
            [
                ['text' => 'Business — ' . __('plan.per_month'), 'callback_data' => 'plan:buy:business:monthly'],
                ['text' => 'Business — ' . __('plan.per_year'),  'callback_data' => 'plan:buy:business:yearly'],
            ],
        ];

        $this->api->sendMessage($chatId, __('plan.choose_plan'), [
            'reply_markup' => ['inline_keyboard' => $kb],
        ]);
    }

    private function confirmSelection(int|string $chatId, string $plan, string $billing): void
    {
        $planTitle = $this->humanPlan($plan);
        $billingTitle = $billing === 'yearly' ? __('plan.per_year') : __('plan.per_month');

        $text = __('plan.selected_plan') . ": {$planTitle}\n"
            . __('plan.billing') . ": {$billingTitle}\n\n"
            . __('plan.payment_soon');

        $this->api->sendMessage($chatId, $text);
    }

    private function humanPlan(string $plan): string
    {
        return match (Features::norm($plan)) {
            Features::FREE     => 'Free',
            Features::STARTER  => 'Starter',
            Features::PRO      => 'Pro',
            Features::BUSINESS => 'Business',
            default            => 'Free',
        };
    }
}
