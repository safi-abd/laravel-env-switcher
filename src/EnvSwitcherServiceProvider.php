<?php

namespace MohammadSafiAbdullah\EnvSwitcher;

use Illuminate\Support\ServiceProvider;
use MohammadSafiAbdullah\EnvSwitcher\Console\LocaliseCommand;
use MohammadSafiAbdullah\EnvSwitcher\Console\ProductioniseCommand;
use MohammadSafiAbdullah\EnvSwitcher\Console\ResetCommand;
use MohammadSafiAbdullah\EnvSwitcher\Console\BackupCommand;
use MohammadSafiAbdullah\EnvSwitcher\Console\StatusCommand;
use MohammadSafiAbdullah\EnvSwitcher\Services\EnvironmentSwitcher;

class EnvSwitcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvironmentSwitcher::class, function () {
            return new EnvironmentSwitcher();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LocaliseCommand::class,
                ProductioniseCommand::class,
                ResetCommand::class,
                BackupCommand::class,
                StatusCommand::class,
            ]);
        }
    }
}