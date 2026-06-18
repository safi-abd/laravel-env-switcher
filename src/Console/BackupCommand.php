<?php

namespace MohammadSafiAbdullah\EnvSwitcher\Console;

use Illuminate\Console\Command;
use MohammadSafiAbdullah\EnvSwitcher\Services\EnvironmentSwitcher;

class BackupCommand extends Command
{
    protected $signature = 'env:backup
                            {--type=manual : Backup label (e.g. manual, pre-deploy)}';

    protected $description = 'Create a named snapshot of the current asset state';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $type = $this->option('type');

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Backup');
        $this->line('  ─────────────────────────────');
        $this->newLine();

        try {
            $switcher->setCommand($this);
            $switcher->createBackup($type);

            $this->newLine();
            $this->line("  <info>✓</info> Backup <comment>{$type}</comment> saved.");
            $this->line('  <fg=gray>Restore with:</> <comment>php artisan env:reset --to=' . $type . '</comment>');
            $this->newLine();
        } catch (\Throwable $e) {
            $this->newLine();
            $this->line('  <e> FAILED </e> ' . $e->getMessage());
            $this->newLine();
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
