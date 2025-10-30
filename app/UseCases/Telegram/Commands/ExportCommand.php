<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\TgUser;
use App\Models\TaxCalculation;
use App\Services\Billing\Features;
use App\Services\Telegram\BotApi;
use Illuminate\Support\Facades\Storage;

final class ExportCommand
{
    public function __construct(private BotApi $api) {}

    public function handle(int|string $chatId): void
    {
        $u = TgUser::query()->byTelegramId($chatId)->first();
        $plan = Features::userPlan($u);

        $canCsv = Features::csvExportEnabled($plan);
        $canPdf = Features::pdfExportEnabled($plan);

        if (!$canCsv && !$canPdf) {
            $this->api->sendMessage($chatId, "Экспорт доступен с планов STARTER/PRO. Открой /plan → «Апгрейд».");
            return;
        }

        $kb = [];
        if ($canCsv) $kb[] = [['text' => 'Экспорт CSV', 'callback_data' => 'export:csv']];
        if ($canPdf) $kb[] = [['text' => 'Экспорт PDF', 'callback_data' => 'export:pdf']];

        $this->api->sendMessage($chatId, "Выберите формат экспорта:", [
            'reply_markup' => ['inline_keyboard' => $kb],
        ]);
    }

    public function handleCallback(int|string $chatId, array $update): void
    {
        $data = (string)($update['callback_query']['data'] ?? '');
        if (!str_starts_with($data, 'export:')) return;

        $fmt = substr($data, strlen('export:')); // csv|pdf

        $u    = TgUser::query()->byTelegramId($chatId)->first();
        $plan = Features::userPlan($u);

        if ($fmt === 'csv' && !Features::csvExportEnabled($plan)) {
            $this->api->sendMessage($chatId, "CSV доступен с плана STARTER. Открой /plan → «Апгрейд».");
            return;
        }
        if ($fmt === 'pdf' && !Features::pdfExportEnabled($plan)) {
            $this->api->sendMessage($chatId, "PDF доступен с плана PRO. Открой /plan → «Апгрейд».");
            return;
        }

        // Берём данные
        $rows = TaxCalculation::query()
            ->with('company:id,name')
            ->where('tg_user_id', $u?->id ?? 0)
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        if ($rows->isEmpty()) {
            $this->api->sendMessage($chatId, "Нет данных для экспорта.");
            return;
        }

        if ($fmt === 'csv') {
            $path = $this->makeCsv($rows->all());
            $this->api->sendDocument($chatId, $path, 'export.csv');
            @unlink($path);
            return;
        }

        // Временная заглушка для PDF
        $this->api->sendMessage($chatId, "PDF-экспорт скоро будет доступен. Пока можно использовать CSV.");
    }

    /** Возвращает абсолютный путь к временному CSV */
    private function makeCsv(array $rows): string
    {
        $tmp = storage_path('app/tmp');
        if (!is_dir($tmp)) @mkdir($tmp, 0777, true);
        $file = $tmp.'/export_'.date('Ymd_His').'.csv';

        $fh = fopen($file, 'w');
        // заголовки
        fputcsv($fh, [
            'period_label','period_from','period_to',
            'income','rate','pay_amount','pay_currency',
            'company','created_at',
        ]);

        foreach ($rows as $r) {
            fputcsv($fh, [
                $r->period_label,
                optional($r->period_from)->format('Y-m-d H:i:s'),
                optional($r->period_to)->format('Y-m-d H:i:s'),
                $r->income,
                $r->rate,
                $r->pay_amount,
                $r->pay_currency,
                $r->company?->name,
                $r->created_at?->format('Y-m-d H:i:s'),
            ]);
        }
        fclose($fh);

        return $file;
    }
}
