<?php

namespace App\UseCases\Telegram\Commands;

use App\Models\TaxCalculation;
use App\Models\TgUser;
use App\Services\Billing\Features;
use App\Services\Telegram\BotApi;
use Illuminate\Support\Facades\Storage;

final class ExportCommand
{
    public function __construct(private BotApi $api) {}

    public function handle(int|string $chatId): void
    {
        /** @var TgUser|null $u */
        $u = TgUser::query()->byTelegramId($chatId)->first();
        if (!$u) {
            $this->api->sendMessage($chatId, "Сначала /start.");
            return;
        }
        $plan = Features::userPlan($u);
        if (!Features::csvExportEnabled($plan)) {
            $this->api->sendMessage(
                $chatId,
                "Экспорт CSV/PDF доступен на тарифах Starter/Pro/Business.\nОткрой /plan → Апгрейд до PRO."
            );
            return;
        }

        // 2) Заберём записи с лимитом плана
        $limit = Features::historyLimitByPlan($plan);
        $rows = TaxCalculation::query()
            ->with('company:id,name')
            ->where('tg_user_id', $u->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->api->sendMessage($chatId, "Нет данных для экспорта.");
            return;
        }

        $dir = 'tmp';
        Storage::disk('local')->makeDirectory($dir);
        $file = sprintf('%s/tax_export_%s_%s.csv', $dir, $u->id, now()->format('Ymd_His'));

        $handle = fopen(storage_path('app/'.$file), 'w');

        fputcsv($handle, [
            'DateFrom','DateTo','Period','Company','Income','Rate(%)','TaxAmount','Currency','CreatedAt',
        ]);

        foreach ($rows as $r) {
            $period = $r->period_label ?: $this->periodFromDates($r->period_from, $r->period_to);
            fputcsv($handle, [
                optional($r->period_from)?->format('Y-m-d') ?: '',
                optional($r->period_to)?->format('Y-m-d') ?: '',
                $period,
                $r->company?->name ?? '',
                $r->income,
                $r->rate,
                $r->pay_amount,
                $r->pay_currency,
                $r->created_at?->format('Y-m-d H:i:s') ?? '',
            ]);
        }
        fclose($handle);

        $caption = "Экспорт расчётов (CSV).";
        $this->api->sendDocument($chatId, storage_path('app/'.$file), $caption);
    }

    private function periodFromDates(?\DateTimeInterface $from, ?\DateTimeInterface $to): string
    {
        if ($from && $to) {
            return $from->format('Y-m-d').'–'.$to->format('Y-m-d');
        }
        if ($from) {
            return $from->format('Y-m-d');
        }
        return '';
    }
}
