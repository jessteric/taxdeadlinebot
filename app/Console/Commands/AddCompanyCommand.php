<?php

namespace App\Console\Commands;

use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\TgUserRepositoryInterface;
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
        $state = new ConversationState($chatId);
        $state->set([
            'step' => 'wait_type',
            'payload' => [],
        ]);

        $kb = [
            [
                ['text' => __('bot.addcompany.type_company'),       'callback_data' => 'ac:type:company'],
            ],
            [
                ['text' => __('bot.addcompany.type_sole_prop'),     'callback_data' => 'ac:type:sole_prop'],
            ],
            [
                ['text' => __('bot.addcompany.type_self_employed'), 'callback_data' => 'ac:type:self_employed'],
            ],
        ];

        $this->api->sendMessage($chatId, __('bot.addcompany.who'), [
            'reply_markup' => ['inline_keyboard' => $kb],
        ]);
    }

    /** Обработка коллбеков: ac:type:<value> */
    public function handleCallback(int|string $chatId, string $data): void
    {
        Log::info('AC_CB', ['chatId' => $chatId, 'data' => $data]);

        if (!str_starts_with($data, 'ac:type:')) {
            return;
        }
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
                $this->api->sendMessage($chatId, __('bot.addcompany.ask_country'));
                return true;

            case 'ask_country':
                $cc = strtoupper(preg_replace('/[^A-Z]/i', '', $text));
                if (strlen($cc) !== 2) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_country'));
                    return true;
                }
                $data['country_code'] = $cc;
                $state->put('payload', $data);
                $state->step('ask_regime');
                $this->api->sendMessage($chatId, __('bot.addcompany.ask_regime'));
                return true;

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
