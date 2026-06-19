<?php

namespace SafiCodes\HostKit;

use Illuminate\Support\ServiceProvider;
use SafiCodes\HostKit\Console\LocaliseCommand;
use SafiCodes\HostKit\Console\ProductioniseCommand;
use SafiCodes\HostKit\Console\ResetCommand;
use SafiCodes\HostKit\Console\BackupCommand;
use SafiCodes\HostKit\Console\StatusCommand;
use SafiCodes\HostKit\Services\HostKit;

class HostKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HostKit::class, function () {
            return new HostKit();
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