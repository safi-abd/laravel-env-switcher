<?php

namespace MohammadSafiAbdullah\EnvSwitcher\Console;

use Illuminate\Console\Command;
use MohammadSafiAbdullah\EnvSwitcher\Services\EnvironmentSwitcher;

class StatusCommand extends Command
{
    protected $signature = 'env:status';

    protected $description = 'Show current asset mode and the state of all managed folders';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $status = $switcher->status();

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Status');
        $this->line('  ──────────────────────────────');
        $this->newLine();

        $modeLabel = match ($status['mode']) {
            'local'      => '<info>LOCAL</info>        (assets in public/)',
            'production' => '<comment>PRODUCTION</comment>   (assets at project root)',
            'conflict'   => '<e> CONFLICT </e>  (assets found in BOTH locations)',
            'unknown'    => '<fg=gray>UNKNOWN</fg=gray>      (no managed asset folders found)',
        };

        $this->line("  Mode: {$modeLabel}");
        $this->newLine();

        // Asset table
        $this->line('  <options=bold>Asset folders:</>');
        $this->newLine();

        $rows = [];
        foreach ($status['assets'] as $dir => $locations) {
            $inPub  = $locations['in_public']  ? '<info>✓ public/' . $dir . '</info>' : '<fg=gray>–</>';
            $inRoot = $locations['in_root']     ? '<comment>✓ ' . $dir . '</comment>'   : '<fg=gray>–</>';
            $rows[] = ['  ' . $dir, $inPub, $inRoot];
        }

        $this->table(
            ['  Folder', 'public/', 'root/'],
            $rows
        );

        // Backups
        $this->newLine();
        if (empty($status['backups'])) {
            $this->line('  <fg=gray>No backups found.</fg=gray>');
        } else {
            $this->line('  <options=bold>Available backups:</>  ' . implode(', ', array_map(
                fn ($b) => "<comment>{$b}</comment>",
                $status['backups']
            )));
        }

        $this->newLine();

        if ($status['mode'] === 'local') {
            $this->line('  Run <comment>php artisan env:productionise</comment> before deploying.');
        } elseif ($status['mode'] === 'production') {
            $this->line('  Run <comment>php artisan env:localise</comment> to switch back for local dev.');
        } elseif ($status['mode'] === 'conflict') {
            $this->line('  Run <comment>php artisan env:reset</comment> to restore a known-good state.');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
