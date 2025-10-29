<?php

namespace App\Providers;

use App\Repositories\CompanyRepository;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DeadlineRuleRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\ObligationRepositoryInterface;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use App\Repositories\Contracts\TaxCalculationRepositoryInterface;
use App\Repositories\Contracts\TgUserRepositoryInterface;
use App\Repositories\DeadlineRuleRepository;
use App\Repositories\EventRepository;
use App\Repositories\ObligationRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\TaxCalculationRepository;
use App\Repositories\TgUserRepository;
use App\Services\Tax\PeriodParser;
use App\Services\Tax\TaxCalculatorService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TgUserRepositoryInterface::class,
            TgUserRepository::class
        );
        $this->app->bind(
            CompanyRepositoryInterface::class,
            CompanyRepository::class
        );
        $this->app->bind(
            ObligationRepositoryInterface::class,
            ObligationRepository::class
        );
        $this->app->bind(
            DeadlineRuleRepositoryInterface::class,
            DeadlineRuleRepository::class
        );
        $this->app->bind(
            EventRepositoryInterface::class,
            EventRepository::class
        );
        $this->app->bind(
            ReminderRepositoryInterface::class,
            ReminderRepository::class
        );
        $this->app->singleton(PeriodParser::class);
        $this->app->singleton(TaxCalculatorService::class);
        $this->app->bind(
            TaxCalculationRepositoryInterface::class,
            TaxCalculationRepository::class
        );

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
