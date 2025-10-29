<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Repositories\Contracts\DeadlineRuleRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Services\Deadline\DeadlineCalculator;
use App\Services\Deadline\HolidayService;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

class DeadlinesGenerateCommand extends Command
{
    protected $signature = 'deadlines:generate {--from=} {--to=}';
    protected $description = 'Generate events (deadlines) for all companies';

    public function __construct(
        private readonly DeadlineRuleRepositoryInterface $rules,
        private readonly EventRepositoryInterface $events
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = new DateTimeImmutable($this->option('from') ?? 'first day of this month');
        $to   = new DateTimeImmutable($this->option('to') ?? 'last day of December this year');

        $this->info("Generating deadlines from {$from->format('Y-m-d')} to {$to->format('Y-m-d')}");

        $companies = Company::query()->with('tgUser')->get();
        $holidays = new HolidayService();

        foreach ($companies as $company) {
            $this->line("→ {$company->name} ({$company->tax_regime})");

            $tz = new DateTimeZone($company->timezone ?? 'UTC');
            $calc = new DeadlineCalculator($holidays, $tz);

            $rules = $this->rules->activeForCountryAndRegime($company->country_code, $company->tax_regime);

            foreach ($rules as $rule) {
                // определяем периоды
                $periods = $this->makePeriods($from, $to, $rule->regime);

                foreach ($periods as [$pStart, $pEnd]) {
                    $due = $calc->dueForPeriod($pStart, $pEnd, $rule->toArray());

                    $this->events->upsertEvent([
                        'company_id'    => $company->id,
                        'obligation_id' => $rule->obligation_id,
                        'period_from'   => $pStart->format('Y-m-d'),
                        'period_to'     => $pEnd->format('Y-m-d'),
                        'due_at'        => $due->format('Y-m-d H:i:s'),
                        'status'        => 'upcoming',
                        'meta'          => [
                            'rule_id' => $rule->id,
                        ],
                    ]);
                }
            }
        }

        $this->info('✅ Deadlines generated.');
        return self::SUCCESS;
    }

    private function makePeriods(DateTimeImmutable $from, DateTimeImmutable $to, string $regime): array
    {
        $periods = [];

        if ($regime === 'annual') {
            $periods[] = [$from, $to];
            return $periods;
        }

        $stepIso = match ($regime) {
            'monthly'   => 'P1M',
            'quarterly' => 'P3M',
            default     => 'P1M',
        };

        $interval = new \DateInterval($stepIso);

        $cursor = $from;
        while ($cursor < $to) {
            $start = $cursor;
            $end   = $start->add($interval)->sub(new \DateInterval('P1D'));
            if ($end > $to) {
                $end = $to;
            }
            $periods[] = [$start, $end];
            $cursor = $start->add($interval);
        }

        return $periods;
    }
}
