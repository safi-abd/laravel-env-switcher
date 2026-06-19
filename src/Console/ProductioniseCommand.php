<?php

namespace SafiCodes\EnvSwitcher\Console;

use Illuminate\Console\Command;
use SafiCodes\EnvSwitcher\Services\EnvironmentSwitcher;

class ProductioniseCommand extends Command
{
    protected $signature = 'env:productionise
                            {--force : Skip confirmation prompt}';

    protected $description = 'Move public/ contents to project root for shared hosting';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Productionise');
        $this->line('  ─────────────────────────────────');
        $this->newLine();

        $mode = $switcher->detectMode();

        if ($mode === 'production') {
            $this->line('  <comment>!</comment> Already in <comment>PRODUCTION</comment> mode.');
            $this->newLine();
            return self::SUCCESS;
        }

        if ($mode === 'conflict') {
            $this->line('  <error> CONFLICT </error> index.php found in BOTH public/ and project root.');
            $this->line('  Run <comment>php artisan env:status</comment> for details, then <comment>php artisan env:reset</comment> to restore a backup.');
            $this->newLine();
            return self::FAILURE;
        }

        if ($mode === 'unknown') {
            $this->line('  <comment>!</comment> Cannot determine mode — <comment>public/index.php</comment> not found.');
            $this->line('  Ensure you have a standard Laravel public/ directory.');
            $this->newLine();
            return self::FAILURE;
        }

        $this->line('  Current mode: <info>LOCAL</info>');
        $this->line('  This will move all <comment>public/</comment> contents to the project root.');
        $this->line('  <comment>index.php</comment> paths will be patched automatically.');
        $this->line('  A backup will be created before any changes.');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('  Continue?')) {
            $this->line('  <comment>!</comment> Cancelled.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->newLine();

        try {
            $switcher->setCommand($this);
            $switcher->productionise();

            $this->newLine();
            $this->line('  <info>✓</info> <options=bold>Switched to PRODUCTION mode.</options=bold>');
            $this->line('  <fg=gray>Tip: Run</> <comment>php artisan env:localise</comment> <fg=gray>to reverse this.</>');
            $this->newLine();
        } catch (\Throwable $e) {
            $this->newLine();
            $this->line('  <error> FAILED </error> ' . $e->getMessage());
            $this->newLine();
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
