<?php

namespace Techamber\EnvSwitcher;

use Illuminate\Support\ServiceProvider;
use Techamber\EnvSwitcher\Console\LocaliseCommand;
use Techamber\EnvSwitcher\Console\ProductioniseCommand;
use Techamber\EnvSwitcher\Console\ResetCommand;
use Techamber\EnvSwitcher\Console\BackupCommand;
use Techamber\EnvSwitcher\Console\StatusCommand;
use Techamber\EnvSwitcher\Services\EnvironmentSwitcher;

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