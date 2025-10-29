<?php

namespace App\Console\Commands;

use App\Models\ReminderSetting;
use App\Services\Telegram\BotApi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class NotifyDueDeadlinesCommand extends Command
{
    protected $signature = 'deadlines:notify-due {--dry-run}';
    protected $description = 'Send deadline reminders according to user settings (days_before, time_local)';

    public function handle(BotApi $api): int
    {
        $nowUtc = CarbonImmutable::now('UTC');
        $dry = (bool)$this->option('dry-run');

        ReminderSetting::query()
            ->with(['tgUser', 'company'])
            ->where('enabled', true)
            ->chunkById(200, function ($chunk) use ($api, $nowUtc, $dry) {
                foreach ($chunk as $s) {
                    if (!$s->tgUser || !$s->company) {
                        continue;
                    }

                    $tz = $s->company->timezone ?: 'UTC';

                    $localNow = $nowUtc->setTimezone($tz);
                    [$hh, $mm] = explode(':', $s->time_local ?: '09:00') + [0,0];

                    if ($localNow->hour !== (int)$hh || (int)$localNow->minute !== (int)$mm) {
                        continue;
                    }

                    // Выписываем дедлайны на указанные дни вперёд
                    $days = $s->days_before ?? [7,3,1];
                    sort($days);
                    $from = $localNow->startOfDay()->timezone('UTC'); // поиск по due_at в UTC

                    foreach ($days as $d) {
                        $target = $localNow->copy()->addDays($d)->startOfDay();

                        $events = $s->company->events()
                            ->with(['obligation'])
                            ->whereDate('due_at', $target->toDateString()) // due_at в UTC, но сравнение по дате
                            ->orderBy('due_at')
                            ->get();

                        if ($events->isEmpty()) {
                            continue;
                        }

                        $lines = ["Напоминание (через {$d} дн.): {$s->company->name}"];
                        foreach ($events as $e) {
                            $dueLocal = $e->due_at?->timezone($tz)->format('Y-m-d');
                            $lines[] = "• {$e->obligation->title} — {$dueLocal} (период {$e->period_from->format('Y-m-d')}–{$e->period_to->format('Y-m-d')})";
                        }

                        $msg = implode("\n", $lines);

                        if ($dry) {
                            $this->info("[DRY] to {$s->tgUser->telegram_id}: {$msg}");
                        } else {
                            $api->sendMessage($s->tgUser->telegram_id, $msg);
                        }
                    }
                }
            });

        return self::SUCCESS;
    }
}
