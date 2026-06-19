<?php

namespace SafiCodes\HostKit\Console;

use Illuminate\Console\Command;
use SafiCodes\HostKit\Services\HostKit;

class LocaliseCommand extends Command
{
    protected $signature = 'env:localise
                            {--force : Skip confirmation prompt}';

    protected $description = 'Move public/ contents back from project root into public/ for local development';

    public function handle(HostKit $switcher): int
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>HOSTKIT</> — Localise');
        $this->line('  ────────────────────────────────');
        $this->newLine();

        $mode = $switcher->detectMode();

        if ($mode === 'local') {
            $this->line('  <comment>!</comment> Already in <comment>LOCAL</comment> mode.');
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
            $this->line('  <comment>!</comment> Cannot determine mode — no index.php found in either location.');
            $this->line('  If this is a fresh project, there is nothing to move yet.');
            $this->newLine();
            return self::FAILURE;
        }

        $this->line('  Current mode: <info>PRODUCTION</info>');
        $this->line('  This will move all previously moved items back into <comment>public/</comment>.');
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
