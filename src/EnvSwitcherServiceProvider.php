<?php

namespace SafiCodes\EnvSwitcher;

use Illuminate\Support\ServiceProvider;
use SafiCodes\EnvSwitcher\Console\LocaliseCommand;
use SafiCodes\EnvSwitcher\Console\ProductioniseCommand;
use SafiCodes\EnvSwitcher\Console\ResetCommand;
use SafiCodes\EnvSwitcher\Console\BackupCommand;
use SafiCodes\EnvSwitcher\Console\StatusCommand;
use SafiCodes\EnvSwitcher\Services\EnvironmentSwitcher;

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