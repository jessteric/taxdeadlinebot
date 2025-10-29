<?php

namespace App\Console\Commands;

use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\TgUserRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;
use App\Services\Telegram\UpdateHelper;
use Illuminate\Support\Str;

readonly class AddCompanyCommand
{
    public function __construct(
        private TgUserRepositoryInterface  $users,
        private CompanyRepositoryInterface $companies,
        private BotApi                     $api
    ) {}

    public function startFlow(int|string $chatId): void
    {
        $state = new ConversationState($chatId);
        $state->set([
            'step' => 'ask_subject_type',
            'payload' => [],
        ]);

        $this->api->sendMessage($chatId, __('bot.addcompany.ask_subject_type'));
    }

    /** Call on every update if state is set */
    public function continueFlow(int|string $chatId, array $update): bool
    {
        $state = new ConversationState($chatId);
        $step = $state->step();
        if (!$step) return false;

        $text = trim(UpdateHelper::text($update));
        $data = $state->get()['payload'] ?? [];

        switch ($step) {
            case 'ask_subject_type':
                $allowedTypes = ['company','sole_prop','self_employed'];
                $type = strtolower($text);
                if (!in_array($type, $allowedTypes, true)) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_subject_type_invalid'));
                    return true;
                }
                $data['subject_type'] = $type;
                $state->put('payload', $data);
                $state->step('ask_name');
                $this->api->sendMessage($chatId, __('bot.addcompany.start')); // "Please send the company name:"
                return true;

            case 'ask_name':
                if ($text === '' || str_starts_with($text, '/')) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_name_invalid'));
                    return true;
                }
                $data['name'] = Str::limit($text, 120, '');

                // если это не company — спросим персональное имя отдельно (можно отличить бренд/название и ФИО)
                if (($data['subject_type'] ?? 'company') !== 'company' && empty($data['person_name'])) {
                    $state->put('payload', $data);
                    $state->step('ask_person_name');
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_person_name'));
                    return true;
                }

                $state->put('payload', $data);
                $state->step('ask_country');
                $this->api->sendMessage($chatId, __('bot.addcompany.ask_country'));
                return true;

            case 'ask_person_name':
                if ($text === '' || str_starts_with($text, '/')) {
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_person_name'));
                    return true;
                }
                $data['person_name'] = Str::limit($text, 160, '');
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

                if (!isset($data['tax_id'])) {
                    $state->step('ask_tax_id');
                    $this->api->sendMessage($chatId, __('bot.addcompany.ask_tax_id'));
                    return true;
                }

            case 'ask_tax_id':
                $data['tax_id'] = ($text === '-' ? null : Str::limit($text, 64, ''));
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

                $this->api->sendMessage(
                    $chatId,
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
