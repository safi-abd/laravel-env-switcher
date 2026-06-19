<?php

namespace SafiCodes\EnvSwitcher\Console;

use Illuminate\Console\Command;
use SafiCodes\EnvSwitcher\Services\EnvironmentSwitcher;

class StatusCommand extends Command
{
    protected $signature = 'env:status';

    protected $description = 'Show current environment mode and the state of public/ contents';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $status = $switcher->status();

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Status');
        $this->line('  ──────────────────────────────');
        $this->newLine();

        $modeLabel = match ($status['mode']) {
            'local'      => '<info>LOCAL</info>        (public/ contains your entry point & assets)',
            'production' => '<comment>PRODUCTION</comment>   (public/ contents moved to project root)',
            'conflict'   => '<error> CONFLICT </error>  (index.php found in BOTH locations)',
            'unknown'    => '<fg=gray>UNKNOWN</fg=gray>      (cannot determine — public/index.php not found)',
        };

        $this->line("  Mode: {$modeLabel}");
        $this->newLine();

        if (!empty($status['public_items'])) {
            $this->line('  <options=bold>In public/:</>');
            foreach ($status['public_items'] as $item) {
                $this->line("    <info>•</info> {$item}");
            }
            $this->newLine();
        }

        if (!empty($status['moved_items'])) {
            $this->line('  <options=bold>Moved to project root:</>');
            foreach ($status['moved_items'] as $item) {
                $this->line("    <comment>•</comment> {$item}");
            }
            $this->newLine();
        }

        if (empty($status['public_items']) && empty($status['moved_items'])) {
            $this->line('  <fg=gray>No items found in public/ or moved to root.</>');
            $this->newLine();
        }

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
