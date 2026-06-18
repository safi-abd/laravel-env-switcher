<?php

namespace MohammadSafiAbdullah\EnvSwitcher\Console;

use Illuminate\Console\Command;
use MohammadSafiAbdullah\EnvSwitcher\Services\EnvironmentSwitcher;

class ProductioniseCommand extends Command
{
    protected $signature = 'env:productionise
                            {--force : Skip confirmation prompt}';

    protected $description = 'Move asset folders from public/ to project root for shared hosting';

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
            $this->line('  <error> CONFLICT </error> Assets found in BOTH public/ and project root.');
            $this->line('  Run <comment>php artisan env:status</comment> for details, then <comment>php artisan env:reset</comment> to restore a backup.');
            $this->newLine();
            return self::FAILURE;
        }

        if ($mode === 'unknown') {
            $this->line('  <comment>!</comment> No managed asset folders found (css, js, images, build).');
            $this->line('  If this is a fresh Vite project, run <comment>npm run build</comment> first.');
            $this->newLine();
            return self::FAILURE;
        }

        $this->line('  Current mode: <info>LOCAL</info>');
        $this->line('  This will move <comment>css/, js/, images/, build/</comment> from <comment>public/</comment> to project root.');
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
