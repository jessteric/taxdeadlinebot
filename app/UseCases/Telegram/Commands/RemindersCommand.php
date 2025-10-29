<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\ReminderSetting;
use App\Models\TgUser;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Services\Telegram\BotApi;
use App\Services\Telegram\ConversationState;

readonly class RemindersCommand
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
        private BotApi $api
    ) {}

    /**
     * Входная точка командой /reminders ИЛИ по callback с префиксом rem:
     * - Если callback rem:* — обрабатываем клик.
     * - Иначе показываем список компаний (или карточку единственной).
     */
    public function handle(int|string $chatId, array $update = []): void
    {
        // callback от inline-кнопок
        $cbData = $update['callback_query']['data'] ?? null;
        if (is_string($cbData) && str_starts_with($cbData, 'rem:')) {
            $this->handleCallback($chatId, $cbData);
            return;
        }

        // обычный вызов /reminders — вывести список компаний
        $list = $this->companies->listForTelegram($chatId);

        if ($list->count() === 0) {
            $this->api->sendMessage($chatId, "Нет компаний. Добавьте через /addcompany.");
            return;
        }

        if ($list->count() === 1) {
            $this->showCompany($chatId, $list->first()->id);
            return;
        }

        $buttons = $list->map(fn($c) => [[
            'text' => $c->name,
            'callback_data' => "rem:pick:{$c->id}",
        ]])->values()->all();

        $this->api->sendMessage($chatId, "Выберите компанию:", [
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
    }

    /**
     * Продолжение диалога (ввод дней/времени после нажатия кнопки).
     * Возвращает true, если обработали апдейт.
     */
    public function continue(int|string $chatId, array $update): bool
    {
        $state = new ConversationState($chatId);
        $step = $state->step();
        if (!$step) return false;

        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!$u) {
            $this->api->sendMessage($chatId, "Не найден Telegram-пользователь, введите /start.");
            $state->clear();
            return true;
        }

        $data = $state->get()['payload'] ?? [];
        $companyId = (int)($data['company_id'] ?? 0);
        $text = trim((string)($update['message']['text'] ?? ''));

        switch ($step) {
            case 'rem_days':
                if (!preg_match('~^\s*\d+(?:\s*,\s*\d+)*\s*$~', $text)) {
                    $this->api->sendMessage($chatId, "Нужен формат: 7,3,1");
                    return true;
                }
                $arr = array_map('intval', preg_split('~\s*,\s*~', $text));
                ReminderSetting::updateOrCreate(
                    ['tg_user_id' => $u->id, 'company_id' => $companyId],
                    ['days_before' => $arr]
                );
                $state->clear();
                $this->showCompany($chatId, $companyId);
                return true;

            case 'rem_time':
                if (!preg_match('~^(?:[01]\d|2[0-3]):[0-5]\d$~', $text)) {
                    $this->api->sendMessage($chatId, "Формат времени: HH:MM (00:00..23:59)");
                    return true;
                }
                ReminderSetting::updateOrCreate(
                    ['tg_user_id' => $u->id, 'company_id' => $companyId],
                    ['time_local' => $text]
                );
                $state->clear();
                $this->showCompany($chatId, $companyId);
                return true;
        }

        return false;
    }

    /**
     * rem:pick:{companyId}
     * rem:toggle:{companyId}
     * rem:days:{companyId}
     * rem:time:{companyId}
     */
    private function handleCallback(int|string $chatId, string $data): void
    {
        $parts = explode(':', $data);
        if (count($parts) < 3) {
            $this->api->sendMessage($chatId, "Некорректное действие.");
            return;
        }

        [, $action, $companyIdRaw] = $parts;
        $companyId = (int)$companyIdRaw;

        $u = TgUser::query()->byTelegramId($chatId)->first();

        switch ($action) {
            case 'pick':
                $this->showCompany($chatId, $companyId);
                break;

            case 'toggle':
                if ($u) {
                    $s = ReminderSetting::firstOrCreate(
                        ['tg_user_id' => $u->id, 'company_id' => $companyId],
                        ['enabled' => true, 'time_local' => '09:00', 'days_before' => [7,3,1]]
                    );
                    $s->enabled = !$s->enabled;
                    $s->save();
                }
                $this->showCompany($chatId, $companyId);
                break;

            case 'days':
                (new ConversationState($chatId))->set([
                    'step' => 'rem_days',
                    'payload' => ['company_id' => $companyId],
                ]);
                $this->api->sendMessage($chatId, "Отправьте список дней через запятую, напр.: 7,3,1");
                break;

            case 'time':
                (new ConversationState($chatId))->set([
                    'step' => 'rem_time',
                    'payload' => ['company_id' => $companyId],
                ]);
                $this->api->sendMessage($chatId, "Отправьте время в формате HH:MM, напр.: 09:30");
                break;

            default:
                $this->api->sendMessage($chatId, "Неизвестное действие.");
        }
    }

    /**
     * Рисуем карточку настроек для конкретной компании (всегда sendMessage).
     */
    private function showCompany(int|string $chatId, int $companyId): void
    {
        $u = TgUser::query()->byTelegramId($chatId)->first();

        if (!$u) {
            $this->api->sendMessage($chatId, "Сначала введите /start.");
            return;
        }

        $s = ReminderSetting::firstOrCreate(
            ['tg_user_id' => $u->id, 'company_id' => $companyId],
            ['enabled' => true, 'time_local' => '09:00', 'days_before' => [7,3,1]]
        );

        $text = "Напоминания:\n"
            . "Состояние: " . ($s->enabled ? "Включено ✅" : "Выключено ❌") . "\n"
            . "Время (лок.): {$s->time_local}\n"
            . "Дни до дедлайна: " . implode(',', $s->days_before);

        $kb = [
            [
                ['text' => $s->enabled ? '❌ Выключить' : '✅ Включить', 'callback_data' => "rem:toggle:{$companyId}"],
            ],
            [
                ['text' => 'Изменить дни (1,3,7)', 'callback_data' => "rem:days:{$companyId}"],
                ['text' => 'Изменить время (HH:MM)', 'callback_data' => "rem:time:{$companyId}"],
            ],
        ];

        $this->api->sendMessage($chatId, $text, [
            'reply_markup' => ['inline_keyboard' => $kb],
        ]);

        (new ConversationState($chatId))->clear();
    }
}
