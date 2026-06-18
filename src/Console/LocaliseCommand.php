<?php

namespace Techamber\EnvSwitcher\Console;

use Illuminate\Console\Command;
use Techamber\EnvSwitcher\Services\EnvironmentSwitcher;

class LocaliseCommand extends Command
{
    protected $signature = 'env:localise
                            {--force : Skip confirmation prompt}';

    protected $description = 'Move asset folders from project root back into public/ for local development';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Localise');
        $this->line('  ────────────────────────────────');
        $this->newLine();

        $mode = $switcher->detectMode();

        if ($mode === 'local') {
            $this->line('  <comment>!</comment> Already in <comment>LOCAL</comment> mode.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->line('  Current mode: <info>PRODUCTION</info>');
        $this->line('  This will move <comment>css/, js/, images/, build/</comment> from project root back into <comment>public/</comment>.');
        $this->line('  A backup will be created automatically before any changes.');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('  Continue?')) {
            $this->line('  <comment>!</comment> Cancelled.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->newLine();

        try {
            $switcher->setCommand($this);
            $switcher->localise();

            $this->newLine();
            $this->line('  <info>✓</info> <options=bold>Switched to LOCAL mode.</options=bold>');
            $this->line('  <fg=gray>Tip: Run</> <comment>php artisan env:productionise</comment> <fg=gray>before deploying.</>');
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
