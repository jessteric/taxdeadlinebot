<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\Company;
use App\Models\TaxCalculation;
use App\Models\TgUser;
use App\Models\UserCompanyTaxPref;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Services\Tax\PeriodParser;
use App\Services\Tax\TaxCalculatorService;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;
use App\Services\Telegram\UpdateHelper;
use Illuminate\Support\Facades\Log;

readonly class TaxCommand
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
        private PeriodParser               $periods,
        private BotApi                     $api
    ) {}

    public function start(int|string $chatId): void
    {
        $list = $this->companies->listForTelegram($chatId);

        if ($list->count() === 0) {
            $this->api->sendMessage($chatId, "Нет компаний/ИП. Добавьте через /addcompany.");
            return;
        }

        $state = new ConversationState($chatId);
        $state->set([
            'flow'    => 'tax',
            'step'    => 'choose_company',
            'payload' => [],
        ]);

        if ($list->count() === 1) {
            /** @var Company $c */
            $c = $list->first();
            $this->pickCompany($chatId, $c->id);
            return;
        }

        $buttons = $list->map(fn(Company $c) => [[
            'text' => "{$c->name} ({$c->pay_currency})",
            'callback_data' => "tax.pick_company:{$c->id}",
        ]])->values()->all();

        $this->api->sendMessage($chatId, "Выберите компанию/ИП для расчёта:", [
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
    }

    public function continue(int|string $chatId, array $update): bool
    {
        $state = new ConversationState($chatId);

        if (isset($update['callback_query']['data']) && str_starts_with($update['callback_query']['data'], 'tax.rate:')) {
            $choice = substr($update['callback_query']['data'], strlen('tax.rate:')); // yes|no
            $data   = $state->get()['payload'] ?? [];

            Log::info('TAX_RATE_CB', [
                'choice'      => $choice,
                'has_period'  => isset($data['period_from'], $data['period_to'], $data['period_label']),
                'has_income'  => isset($data['income']),
                'has_cand'    => isset($data['candidate_rate']),
                'company_id'  => $data['company_id'] ?? null,
            ]);

            if ($choice === 'yes') {
                // проверяем, что всё готово к финализации
                if (!isset($data['candidate_rate'], $data['income'], $data['period_from'], $data['period_to'], $data['period_label'])) {
                    Log::warning('TAX_RATE_CB_INCOMPLETE_STATE', $data);
                    $this->api->sendMessage($chatId, "Кажется, не хватает данных для расчёта. Давай начнём заново: /tax");
                    $state->clear();
                    return true;
                }
                $data['rate'] = (float)$data['candidate_rate'];
                $state->put('payload', $data);

                $this->finishAndReport($chatId, $data);
                $state->clear();
                return true;
            }

            // choice === 'no' → спрашиваем ставку руками
            $company = $this->companies->findOwnedBy($chatId, (int)($data['company_id'] ?? 0));
            $hint = $company && $company->default_tax_rate !== null
                ? " (по умолчанию {$company->default_tax_rate}%)"
                : '';
            $this->api->sendMessage($chatId, "Укажи процент налога{$hint}:");
            $state->step('ask_rate');
            return true;
        }

        $step = $state->step();
        if (!$step) return false;

        $data = $state->get()['payload'] ?? [];

        // обработка выбора компании
        if (isset($update['callback_query']['data']) && str_starts_with($update['callback_query']['data'], 'tax.pick_company:')) {
            $companyId = (int)substr($update['callback_query']['data'], strlen('tax.pick_company:'));
            $this->pickCompany($chatId, $companyId);
            return true;
        }

        $text = trim((string)UpdateHelper::text($update));

        switch ($step) {
            case 'ask_period':
                try {
                    $p = $this->periods->parse($text);
                    $data['period_from'] = $p['from'];
                    $data['period_to']   = $p['to'];
                    $data['period_label']= $p['label'];
                    $state->put('payload', $data);
                } catch (\Throwable) {
                    $this->api->sendMessage($chatId, "Укажи период (месяц или квартал). Примеры:\n• 2025-10\n• 2025-Q4");
                    return true;
                }

                $this->api->sendMessage($chatId, "Введите сумму дохода (например: 2500)");
                $state->step('ask_income');
                return true;

            case 'ask_income':
                if (!preg_match('~^\s*([0-9]+(?:[\,\.][0-9]{1,2})?)\s*$~u', $text, $m)) {
                    $this->api->sendMessage($chatId, "Введите сумму дохода, например: 2500");
                    return true;
                }
                $amount = (float)str_replace(',', '.', $m[1]);
                $data['income'] = $amount;
                $state->put('payload', $data);

                // подсказка прошлой ставки
                $pref = $this->loadPref($chatId, (int)$data['company_id']);
                if ($pref && $pref->last_tax_rate !== null) {
                    $state->step('confirm_rate');
                    $data['candidate_rate'] = (float)$pref->last_tax_rate;
                    $state->put('payload', $data);

                    $kb = [
                        [
                            ['text' => '✅ Да',  'callback_data' => 'tax.rate:yes'],
                            ['text' => '✏️ Нет', 'callback_data' => 'tax.rate:no'],
                        ],
                    ];

                    $this->api->sendMessage(
                        $chatId,
                        "Ставка как в прошлый раз: {$pref->last_tax_rate}% — оставить?",
                        ['reply_markup' => ['inline_keyboard' => $kb]]
                    );
                    $state->step('confirm_rate');
                    return true;
                }

                // иначе — спросим ставку (с хинтом из company->default_tax_rate)
                $company = $this->companies->findOwnedBy($chatId, (int)$data['company_id']);
                $hint = $company && $company->default_tax_rate !== null
                    ? " (по умолчанию {$company->default_tax_rate}%)"
                    : '';
                $this->api->sendMessage($chatId, "Процент налога{$hint}:");
                $state->step('ask_rate');
                return true;

            case 'confirm_rate':
                $norm = str_replace(',', '.', trim(mb_strtolower($text)));

                // Если пользователь сразу прислал число — трактуем как новую ставку
                if ($norm !== '' && is_numeric($norm)) {
                    $data['rate'] = (float)$norm;
                    $state->put('payload', $data);
                    $this->finishAndReport($chatId, $data);
                    $state->clear();
                    return true;
                }

                // да/yes → берём candidate_rate
                $yes = in_array($norm, ['y','yes','да','д','угу','ok','ок'], true);
                if ($yes) {
                    $data['rate'] = (float)$data['candidate_rate'];
                    $state->put('payload', $data);
                    $this->finishAndReport($chatId, $data);
                    $state->clear();
                    return true;
                }

                // всё остальное → спросить новую ставку с подсказкой дефолта
                $company = $this->companies->findOwnedBy($chatId, (int)$data['company_id']);
                $hint = $company && $company->default_tax_rate !== null
                    ? " (по умолчанию {$company->default_tax_rate}%)"
                    : '';
                $this->api->sendMessage($chatId, "Укажи процент налога{$hint}:");
                $state->step('ask_rate');
                return true;

            case 'ask_rate':
                $rate = null;
                $companyId = (int)($data['company_id'] ?? 0);
                $company = $this->companies->findOwnedBy($chatId, $companyId);

                Log::info('TAX_ASK_RATE_INPUT', [
                    'chat_id'        => (string)$chatId,
                    'company_id'     => $companyId,
                    'text_raw'       => $text,
                    'default_present'=> $company?->default_tax_rate !== null,
                    'default_value'  => $company?->default_tax_rate,
                ]);

                // 1) Пустой ввод → пробуем дефолт компании
                if ($text === '') {
                    if ($company && $company->default_tax_rate !== null) {
                        $rate = (float)$company->default_tax_rate;
                        Log::info('TAX_ASK_RATE_TAKE_DEFAULT_ON_EMPTY', [
                            'chat_id'    => (string)$chatId,
                            'company_id' => $companyId,
                            'rate'       => $rate,
                        ]);
                    }
                }
                // 2) Пользователь ввёл число → берём его
                elseif (preg_match('~^\s*([0-9]+(?:[,.][0-9]{1,3})?)\s*%?\s*$~u', $text, $m)) {
                    $rate = (float)str_replace(',', '.', $m[1]);
                    Log::info('TAX_ASK_RATE_TAKE_USER_INPUT', [
                        'chat_id'    => (string)$chatId,
                        'company_id' => $companyId,
                        'rate'       => $rate,
                    ]);
                }
                // 3) Иначе → fallback к дефолту компании, если есть
                else {
                    if ($company && $company->default_tax_rate !== null) {
                        $rate = (float)$company->default_tax_rate;
                        Log::info('TAX_ASK_RATE_FALLBACK_DEFAULT', [
                            'chat_id'    => (string)$chatId,
                            'company_id' => $companyId,
                            'rate'       => $rate,
                        ]);
                    } else {
                        Log::warning('TAX_ASK_RATE_NO_MATCH_AND_NO_DEFAULT', [
                            'chat_id'    => (string)$chatId,
                            'company_id' => $companyId,
                            'text_raw'   => $text,
                        ]);
                    }
                }

                if ($rate === null) {
                    $this->api->sendMessage($chatId, "Укажи процент, например: 2 или 2.5");
                    return true;
                }

                Log::info('TAX_ASK_RATE_FINAL', [
                    'chat_id'    => (string)$chatId,
                    'company_id' => $companyId,
                    'rate'       => $rate,
                ]);

                $data['rate'] = $rate;
                $state->put('payload', $data);

                $this->finishAndReport($chatId, $data);
                $state->clear();
                return true;
        }

        return false;
    }

    private function pickCompany(int|string $chatId, int $companyId): void
    {
        $company = $this->companies->findOwnedBy($chatId, $companyId);
        if (!$company) {
            $this->api->sendMessage($chatId, "Компания не найдена.");
            return;
        }
        $state = new ConversationState($chatId);
        $state->set(['step' => 'ask_period', 'payload' => ['company_id' => $companyId]]);
        $this->api->sendMessage(
            $chatId,
            "Укажи период расчёта. Примеры:\n• 2025-10 (месяц)\n• 2025-Q4 (квартал)"
        );
    }

    private function loadPref(int|string $chatId, int $companyId): ?UserCompanyTaxPref
    {
        /** @var TgUser|null $u */
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!$u) return null;

        return UserCompanyTaxPref::query()
            ->where('tg_user_id', $u->id)
            ->where('company_id', $companyId)
            ->first();
    }

    private function savePref(int|string $chatId, int $companyId, float $rate, string $periodLabel): void
    {
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!$u) return;

        UserCompanyTaxPref::updateOrCreate(
            ['tg_user_id' => $u->id, 'company_id' => $companyId],
            ['last_tax_rate' => $rate, 'last_period' => $periodLabel]
        );
    }

    private function finishAndReport(int|string $chatId, array $data): void
    {
        Log::info('TAX_FINISH_INPUT', [
            'company_id'   => $data['company_id'] ?? null,
            'period_from'  => $data['period_from'] ?? null,
            'period_to'    => $data['period_to'] ?? null,
            'period_label' => $data['period_label'] ?? null,
            'income'       => $data['income'] ?? null,
            'rate'         => $data['rate'] ?? null,
        ]);

        foreach (['company_id','period_from','period_to','period_label','income','rate'] as $k) {
            if (!isset($data[$k])) {
                $this->api->sendMessage($chatId, "Не хватает данных для расчёта ({$k}). Давай начнём заново: /tax");
                Log::warning('TAX_MISSING_FIELD_BEFORE_CREATE', ['missing' => $k, 'payload' => $data]);
                return;
            }
        }

        $company = $this->companies->findOwnedBy($chatId, (int)$data['company_id']);
        if (!$company) {
            $this->api->sendMessage($chatId, "Компания не найдена.");
            return;
        }

        $res = app(TaxCalculatorService::class)
            ->calc($data['income'], $data['rate'], $company);

        // Сохраним историю
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if ($u) {
            Log::info('TAX_CREATE_PAYLOAD', [
                'payload' => [
                    'tg_user_id'   => $u->id,
                    'company_id'   => $company->id,
                    'period_from'  => $data['period_from'] ?? null,
                    'period_to'    => $data['period_to'] ?? null,
                    'period_label' => $data['period_label'] ?? null,
                    'income'       => $res['income'] ?? null,
                    'rate'         => $res['rate'] ?? null,
                    'pay_amount'   => $res['pay_amount'] ?? null,
                    'pay_currency' => $res['pay_currency'] ?? null,
                ],
            ]);

            TaxCalculation::create([
                'tg_user_id'   => $u->id,
                'company_id'   => $company->id,
                'period_from'  => $data['period_from'] ?? null,
                'period_to'    => $data['period_to'] ?? null,
                'period_label' => $data['period_label'] ?? '',
                'income'       => $res['income'],
                'rate'         => $res['rate'],
                'pay_amount'   => $res['pay_amount'],
                'pay_currency' => $res['pay_currency'],
            ]);

            $this->savePref($chatId, $company->id, (float)$res['rate'], (string)($data['period_label'] ?? ''));
        }

        $lines = [];
        $lines[] = "Компания: {$company->name}";
        if (!empty($data['period_label'])) {
            $lines[] = "Период: {$data['period_label']}";
        }
        $lines[] = "Доход: " . number_format($res['income'], 2, '.', ' ');
        $lines[] = "Ставка: {$res['rate']}%";
        $lines[] = "К оплате: <b>" . number_format($res['pay_amount'], 2, '.', ' ') . " {$res['pay_currency']}</b>";

        $this->api->sendMessage($chatId, implode("\n", $lines), ['parse_mode' => 'HTML']);
    }
}
