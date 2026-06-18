<?php

namespace MohammadSafiAbdullah\EnvSwitcher\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use MohammadSafiAbdullah\EnvSwitcher\Services\EnvironmentSwitcher;

class ResetCommand extends Command
{
    protected $signature = 'env:reset
                            {--to=previous : Which backup to restore (previous or original)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Restore asset folders from a backup (previous or original)';

    public function handle(EnvironmentSwitcher $switcher): int
    {
        $type       = $this->option('to');
        $backupPath = base_path('.env-switcher-backups/' . $type);

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ENV SWITCHER</> — Reset');
        $this->line('  ────────────────────────────');
        $this->newLine();

        if (!File::isDirectory($backupPath)) {
            $this->line("  <e> ERROR </e> Backup '<comment>{$type}</comment>' not found at:");
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
        $this->line("  This will overwrite all current asset folders.");
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('  Continue?')) {
            $this->line('  <comment>!</comment> Cancelled.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->newLine();

        try {
            // Copy to a temp location first so we can verify before wiping current state
            $tmpPath = base_path('.env-switcher-tmp-restore-' . time());
            File::makeDirectory($tmpPath, 0755, true);

            if (File::isDirectory($backupPath . '/public')) {
                File::copyDirectory($backupPath . '/public', $tmpPath . '/public');
            }
            if (File::isDirectory($backupPath . '/root')) {
                File::copyDirectory($backupPath . '/root', $tmpPath . '/root');
            }

            // Only wipe current state AFTER temp copy succeeded
            foreach ($switcher->getAssetDirs() as $dir) {
                File::deleteDirectory(public_path($dir));
                File::deleteDirectory(base_path($dir));
            }

            // Clean .htaccess from both locations before restore
            if (File::isFile(public_path('.htaccess'))) {
                File::delete(public_path('.htaccess'));
            }
            if (File::isFile(base_path('.htaccess'))) {
                File::delete(base_path('.htaccess'));
            }

            // Restore from temp
            if (File::isDirectory($tmpPath . '/public')) {
                File::copyDirectory($tmpPath . '/public', public_path());
            }
            if (File::isDirectory($tmpPath . '/root')) {
                File::copyDirectory($tmpPath . '/root', base_path());
            }

            // Clean up temp
            File::deleteDirectory($tmpPath);

            $this->line("  <info>✓</info> Restored from <comment>{$type}</comment> backup.");
            $this->newLine();
        } catch (\Throwable $e) {
            // Attempt to clean up temp dir
            if (isset($tmpPath) && File::isDirectory($tmpPath)) {
                File::deleteDirectory($tmpPath);
            }

            $this->newLine();
            $this->line('  <e> FAILED </e> ' . $e->getMessage());
            $this->line('  <fg=gray>Your backup is still intact at:</> <comment>' . $backupPath . '</comment>');
            $this->newLine();
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
