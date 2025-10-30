<?php

namespace App\Services\Billing;

use App\Models\TgUser;

final class Features
{
    public const FREE     = 'free';
    public const STARTER  = 'starter';
    public const PRO      = 'pro';
    public const BUSINESS = 'business';

    /** Нормализуем имя плана */
    public static function norm(string $plan): string
    {
        $p = strtolower(trim($plan));
        return in_array($p, [self::FREE, self::STARTER, self::PRO, self::BUSINESS], true) ? $p : self::FREE;
    }

    public static function userPlan(?TgUser $u): string
    {
        if (app()->environment('local')) {
            $override = config('billing.dev.force_plan');
            if (is_string($override) && $override !== '') {
                return self::norm($override);
            }
        }

        $p = $u?->plan ?: self::FREE;
        return self::norm($p);
    }

    public static function historyLimitByPlan(string $plan): int
    {
        return match (self::norm($plan)) {
            self::FREE     => 5,
            self::STARTER  => 100,
            self::PRO,
            self::BUSINESS => 10_000_000,
        };
    }

    public static function historyLimitLabel(string $plan): string
    {
        return match (self::norm($plan)) {
            self::FREE    => __('plan.history_free'),
            self::STARTER => __('plan.history_starter'),
            default       => __('plan.history_unlim'),
        };
    }

    public static function csvExportEnabled(string $plan): bool
    {
        return in_array(self::norm($plan), [self::STARTER, self::PRO, self::BUSINESS], true);
    }

    public static function pdfExportEnabled(string $plan): bool
    {
        return in_array(self::norm($plan), [self::PRO, self::BUSINESS], true);
    }

    public static function exportEnabled(string $plan): bool
    {
        return self::csvExportEnabled($plan) || self::pdfExportEnabled($plan);
    }

    public static function fxEnabled(string $plan): bool
    {
        return in_array(self::norm($plan), [self::PRO, self::BUSINESS], true);
    }

    public static function teamEnabled(string $plan): bool
    {
        return self::norm($plan) === self::BUSINESS;
    }

    public static function isProOrHigherPlan(string $plan): bool
    {
        return in_array(self::norm($plan), [self::PRO, self::BUSINESS], true);
    }

    public static function historyLimit(?TgUser $u): int
    {
        return self::historyLimitByPlan(self::userPlan($u));
    }

    public static function exportEnabledFor(?TgUser $u): bool
    {
        $byPlan = self::exportEnabled(self::userPlan($u));
        $flag   = app()->environment('local') && config('billing.dev.flags.export');
        return $byPlan || $flag;
    }

    public static function pdfExportEnabledFor(?TgUser $u): bool
    {
        return self::pdfExportEnabled(self::userPlan($u));
    }

    public static function fxEnabledFor(?TgUser $u): bool
    {
        $byPlan = self::fxEnabled(self::userPlan($u));
        $flag   = app()->environment('local') && config('billing.dev.flags.fx');
        return $byPlan || $flag;
    }

    public static function isProOrHigher(?TgUser $u): bool
    {
        return self::isProOrHigherPlan(self::userPlan($u));
    }

    /**
     * @param TgUser|null $u
     * @return bool
     */
    public static function isPro(?TgUser $u): bool
    {
        return self::userPlan($u) === self::PRO;
    }

    public static function companyLimitByPlan(string $plan): int
    {
        return match (self::norm($plan)) {
            self::FREE     => 1,
            self::STARTER  => 2,
            self::PRO      => 10,
            self::BUSINESS => 50,
        };
    }

    public static function companyLimitLabel(string $plan): string
    {
        return match (self::norm($plan)) {
            self::FREE     => '1',
            self::STARTER  => '2',
            self::PRO      => '10',
            self::BUSINESS => '50',
        };
    }

    public static function companyLimitFor(?TgUser $u): int
    {
        return self::companyLimitByPlan(self::userPlan($u));
    }
}
