<?php

namespace App\Console\Commands;

use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\TgUserRepositoryInterface;
use App\Services\Billing\Features;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;
use App\Services\Telegram\UpdateHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

readonly class AddCompanyCommand
{
    public function __construct(
        private TgUserRepositoryInterface  $users,
        private CompanyRepositoryInterface $companies,
        private BotApi                     $api
    ) {}

    /** /addcompany — старт с кнопками типа субъекта */
    public function startFlow(int|string $chatId): void
    {
        Log::info('AC_START_FLOW', ['chatId' => $chatId]); // <— добавили

        $state = new ConversationState($chatId);
        $state->set([
            'step'    => 'wait_type',
            'payload' => [],
        ]);

        $kb = [
            [ ['text' => __('bot.addcompany.type_company'),       'callback_data' => 'ac:type:company'] ],
            [ ['text' => __('bot.addcompany.type_sole_prop'),     'callback_data' => 'ac:type:sole_prop'] ],
            [ ['text' => __('bot.addcompany.type_self_employed'), 'callback_data' => 'ac:type:self_employed'] ],
        ];

        // подстрахуем отправку
        try {
            $this->api->sendMessage($chatId, __('bot.addcompany.who'), [
                'reply_markup' => ['inline_keyboard' => $kb],
            ]);
            Log::info('AC_SENT_WHO', ['chatId' => $chatId]);
        } catch (\Throwable $e) {
            Log::error('AC_SEND_FAILED', ['e' => $e->getMessage()]);
            throw $e;
        }
        $user = $this->users->findByTelegramId($chatId);
        $plan = Features::userPlan($user);
        $limit = Features::companyLimitByPlan($plan);

        // считаем текущие компании (либо сделай companies->countForTelegram($chatId), если есть)
        $current = $this->companies->listForTelegram($chatId)->count();

        if ($current >= $limit) {
            $this->api->sendMessage(
                $chatId,
                __('plan.company_limit_reached', ['limit' => $limit])
            );

            $kb = [
                [
                    ['text' => __('plan.upgrade_to_pro_button'), 'callback_data' => 'plan:upgrade'],
                ],
                [
                    ['text' => __('plan.details_button'), 'callback_data' => 'plan:details'],
                ],
            ];
            $this->api->sendMessage(
                $chatId,
                __('plan.upgrade_hint'),
                ['reply_markup' => ['inline_keyboard' => $kb]]
            );
            return;
        }
    }

    /** Обработка коллбеков: ac:type:<value> */
    public function handleCallback(int|string $chatId, string $data): void
    {
        Log::info('AC_CB', ['chatId' => $chatId, 'data' => $data]);

        if (str_starts_with($data, 'ac:type:')) {
            $type = substr($data, strlen('ac:type:'));

            $state = new ConversationState($chatId);
            $cur = $state->get() ?? ['step' => null, 'payload' => []];
            $payload = $cur['payload'] ?? [];
            $payload['subject_type'] = $type;

            $state->set([
                'step'    => 'ask_name',
                'payload' => $payload,
            ]);

            $this->api->sendMessage($chatId, __('bot.addcompany.ask_name'));
            return;
        }

        if (str_starts_with($data, 'ac:country:')) {
            $cc = strtoupper(substr($data, strlen('ac:country:'))); // GE|BY|RU|AM|DE|PL|KZ|OTHER

            $state = new ConversationState($chatId);
            $cur = $state->get() ?? ['step' => null, 'payload' => []];
            $payload = $cur['payload'] ?? [];

            if ($cc === 'OTHER') {
                // Просим ввести ISO-код вручную
                $state->set([
                    'step'    => 'ask_country_manual',
                    'payload' => $payload,
                ]);
                $this->api->sendMessage($chatId, "Введи двухбуквенный код страны (ISO-3166-1 alpha-2), напр.: GE, BY, RU, AM, DE, PL, KZ");
                return;
            }

            $payload['country_code'] = $cc;
            $state->set([
                'step'    => 'ask_regime',
                'payload' => $payload,
            ]);

            $this->api->sendMessage($chatId, __('bot.addcompany.ask_regime'));
        }
    }

    /** Текстовые шаги */
    public function continueFlow(int|string $chatId, array $update): bool
    {
        // не перехватываем новые /команды
        $incomingText = trim((string)($update['message']['text'] ?? ''));
        if ($incomingText !== '' && str_starts_with($incomingText, '/')) {
            return false;
        }

        $state = new ConversationState($chatId);
        $step = $state->step();
        if (!$step) return false;

        $text = trim(UpdateHelper::text($update));
        $data = $state->get()['payload'] ?? [];

        switch ($step) {
            case 'wait_type':
                // Если вместо нажатия кнопки пользователь что-то напишет — просим выбрать кнопками.
                $this->api->sendMessage($chatId, __('bot.addcompany.who'));
                return true;

            case 'ask_name':
                if ($text === '' || str_starts_with($text, '/')) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_name_invalid'));
                    return true;
                }

                $data['name'] = Str::limit($text, 120, '');
                $state->put('payload', $data);
                $state->step('ask_country');

                $kb = [
                    [
                        ['text' => 'Грузия 🇬🇪',    'callback_data' => 'ac:country:GE'],
                        ['text' => 'Беларусь 🇧🇾',  'callback_data' => 'ac:country:BY'],
                    ],
                    [
                        ['text' => 'Россия 🇷🇺',    'callback_data' => 'ac:country:RU'],
                        ['text' => 'Армения 🇦🇲',   'callback_data' => 'ac:country:AM'],
                    ],
                    [
                        ['text' => 'Германия 🇩🇪',  'callback_data' => 'ac:country:DE'],
                        ['text' => 'Польша 🇵🇱',    'callback_data' => 'ac:country:PL'],
                    ],
                    [
                        ['text' => 'Казахстан 🇰🇿', 'callback_data' => 'ac:country:KZ'],
                        ['text' => 'Другая…',       'callback_data' => 'ac:country:OTHER'],
                    ],
                ];

                $this->api->sendMessage(
                    $chatId,
                    // сделай текст нейтральным: «Выберите страну кнопкой или введите код»
                    __('bot.addcompany.ask_country_buttons'),
                    ['reply_markup' => ['inline_keyboard' => $kb]]
                );
                return true;

            case 'ask_country':
                $rawText = (string)($update['message']['text'] ?? '');
                $txt = trim($rawText);

                $ccTyped = strtoupper(preg_replace('/[^A-Z]/i', '', $txt));
                if ($ccTyped !== '' && strlen($ccTyped) === 2) {
                    $data['country_code'] = $ccTyped;
                    $state->put('payload', $data);
                    $state->step('ask_regime');
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_regime'));
                    return true;
                }

                // если пришла абракадабра — просто снова покажем те же кнопки
                $kb = [
                    [
                        ['text' => 'Грузия 🇬🇪',    'callback_data' => 'ac:country:GE'],
                        ['text' => 'Беларусь 🇧🇾',  'callback_data' => 'ac:country:BY'],
                    ],
                    [
                        ['text' => 'Россия 🇷🇺',    'callback_data' => 'ac:country:RU'],
                        ['text' => 'Армения 🇦🇲',   'callback_data' => 'ac:country:AM'],
                    ],
                    [
                        ['text' => 'Германия 🇩🇪',  'callback_data' => 'ac:country:DE'],
                        ['text' => 'Польша 🇵🇱',    'callback_data' => 'ac:country:PL'],
                    ],
                    [
                        ['text' => 'Казахстан 🇰🇿', 'callback_data' => 'ac:country:KZ'],
                        ['text' => 'Другая…',       'callback_data' => 'ac:country:OTHER'],
                    ],
                ];

                $this->api->sendMessage(
                    $chatId,
                    __('bot.addcompany.ask_country_buttons'),
                    ['reply_markup' => ['inline_keyboard' => $kb]]
                );
                return true;

            case 'ask_country_manual':
            {
                $txt = trim((string)($update['message']['text'] ?? ''));
                Log::info('AC_ASK_COUNTRY_MANUAL_INPUT', ['txt' => $txt]);

                $cc = strtoupper(preg_replace('/[^A-Z]/i', '', $txt));
                if (strlen($cc) !== 2) {
                    $this->api->sendMessage($chatId, "Неверный код. Введи двухбуквенный код ISO, напр.: GE, BY, RU, AM, DE, PL, KZ");
                    return true;
                }
                $data['country_code'] = $cc;
                $state->put('payload', $data);
                $state->step('ask_regime');
                $this->api->sendMessage($chatId, __('bot.addcompany.ask_regime'));
                return true;
            }

            case 'ask_regime':
                $allowed = ['monthly','quarterly','annual'];
                $val = strtolower($text);
                if (!in_array($val, $allowed, true)) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_regime'));
                    return true;
                }
                $data['tax_regime'] = $val;
                $state->put('payload', $data);
                $state->step('ask_timezone');
                $this->api->sendMessage($chatId, __('bot.addcompany.ask_timezone'));
                return true;

            case 'ask_timezone':
                try {
                    new \DateTimeZone($text);
                } catch (\Throwable) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_timezone'));
                    return true;
                }
                $data['timezone'] = $text;
                $state->put('payload', $data);

                // persist
                $user = $this->users->findByTelegramId($chatId);
                if (!$user instanceof TgUser) {
                    $user = $this->users->upsertFromTelegram(
                        $chatId,
                        UpdateHelper::username($update),
                        UpdateHelper::locale($update)
                    );
                }

                $company = $this->companies->createForUser($user->id, $data);
                $state->clear();

                $this->api->sendMessage($chatId,
                    __('bot.addcompany.saved', [
                        'name'    => $company->name,
                        'country' => $company->country_code,
                        'regime'  => $company->tax_regime,
                        'tz'      => $company->timezone,
                    ])
                );
                return true;
        }

        return false;
    }
}
