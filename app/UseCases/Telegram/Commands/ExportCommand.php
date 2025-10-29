<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\TgUser;
use App\Services\Billing\Features;
use App\Services\Telegram\BotApi;
use Illuminate\Support\Facades\DB;

class ExportCommand
{
    public function __construct(private BotApi $api) {}

    public function handle(int|string $chatId): void
    {
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!Features::canExport($u)) {
            $this->api->sendMessage($chatId, "Экспорт CSV/PDF доступен в PRO. Открой /plan для апгрейда.");
            return;
        }

        // Сгружаем последние N или всё
        $rows = DB::table('tax_calculations as t')
            ->join('companies as c', 'c.id', '=', 't.company_id')
            ->where('t.tg_user_id', $u->id)
            ->orderByDesc('t.id')
            ->limit(1000)
            ->get([
                't.created_at',
                'c.name as company',
                't.period_label as period',
                't.income',
                't.rate',
                't.pay_amount as tax',
                't.pay_currency as currency',
            ])
            ->map(fn($r) => (array)$r)
            ->all();

        $csv = $this->buildCsv($rows);
        $this->api->sendDocument($chatId, 'tax_history.csv', $csv);
    }

    private function buildCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['Date','Company','Period','Income','Rate%','Tax','Currency']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['created_at'] ?? '',
                $r['company'] ?? '',
                $r['period'] ?? '',
                $r['income'] ?? '',
                $r['rate'] ?? '',
                $r['tax'] ?? '',
                $r['currency'] ?? '',
            ]);
        }
        rewind($fh);
        return stream_get_contents($fh);
    }
}
