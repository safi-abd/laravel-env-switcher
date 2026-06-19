<?php

namespace SafiCodes\EnvSwitcher\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SafiCodes\EnvSwitcher\Services\EnvironmentSwitcher;

class ResetCommand extends Command
{
    protected $signature = 'env:reset
                            {--to=previous : Which backup to restore (e.g. previous, original, pre-deploy)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Restore public/ contents from a backup';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $type       = $this->option('to');
        $backupPath = base_path('.env-switcher-backups/' . $type);

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Reset');
        $this->line('  ────────────────────────────');
        $this->newLine();

        if (!File::isDirectory($backupPath)) {
            $this->line("  <error> ERROR </error> Backup '<comment>{$type}</comment>' not found at:");
            $this->line("  <fg=gray>{$backupPath}</>");
            $this->newLine();

            $backupsRoot = base_path('.env-switcher-backups');
            if (File::isDirectory($backupsRoot)) {
                $available = array_map('basename', File::directories($backupsRoot));
                if (!empty($available)) {
                    $this->line('  Available backups: <info>' . implode(', ', $available) . '</info>');
                }
            } else {
                $this->line('  No backups exist yet. Run any switch command first.');
            }

            $this->newLine();
            return self::FAILURE;
        }

        $this->line("  Restoring from backup: <comment>{$type}</comment>");
        $this->line("  This will overwrite current public/ contents and any moved items.");
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('  Continue?')) {
            $this->line('  <comment>!</comment> Cancelled.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->newLine();

        try {
            $tmpPath = base_path('.env-switcher-tmp-restore-' . time());
            File::makeDirectory($tmpPath, 0755, true);

            if (File::isDirectory($backupPath . '/public')) {
                File::copyDirectory($backupPath . '/public', $tmpPath . '/public');
            }
            if (File::isDirectory($backupPath . '/root')) {
                File::copyDirectory($backupPath . '/root', $tmpPath . '/root');
            }

            // Wipe current public/ contents (except symlinks and .gitignore)
            foreach ($switcher->getPublicItems() as $item) {
                $path = public_path($item);
                if (File::isDirectory($path)) {
                    File::deleteDirectory($path);
                } elseif (File::isFile($path)) {
                    File::delete($path);
                }
            }

            // Wipe moved items at root
            foreach ($switcher->getMovedItems() as $item) {
                $path = base_path($item);
                if (File::isDirectory($path)) {
                    File::deleteDirectory($path);
                } elseif (File::isFile($path)) {
                    File::delete($path);
                }
            }

            // Restore from temp
            if (File::isDirectory($tmpPath . '/public')) {
                File::copyDirectory($tmpPath . '/public', public_path());
            }
            if (File::isDirectory($tmpPath . '/root')) {
                File::copyDirectory($tmpPath . '/root', base_path());
            }

            // Restore state file from backup (or delete if backup had none)
            $backupStatePath = $backupPath . '/.env-switcher.json';
            $statePath = base_path('.env-switcher.json');
            if (File::isFile($backupStatePath)) {
                File::copy($backupStatePath, $statePath);
            } elseif (File::isFile($statePath)) {
                File::delete($statePath);
            }

            File::deleteDirectory($tmpPath);

            $this->line("  <info>✓</info> Restored from <comment>{$type}</comment> backup.");
            $this->newLine();
        } catch (\Throwable $e) {
            if (isset($tmpPath) && File::isDirectory($tmpPath)) {
                File::deleteDirectory($tmpPath);
            }

            $this->newLine();
            $this->line('  <error> FAILED </error> ' . $e->getMessage());
            $this->line('  <fg=gray>Your backup is still intact at:</> <comment>' . $backupPath . '</comment>');
            $this->newLine();
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
