<?php

namespace MohammadSafiAbdullah\EnvSwitcher\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EnvironmentSwitcher
{
    protected ?Command $command = null;

    /**
     * Asset folder names managed by this package.
     */
    protected array $assetDirs = ['css', 'js', 'images', 'build'];

    public function getAssetDirs(): array
    {
        return $this->assetDirs;
    }

    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    /* ---------------- MODE DETECTION ---------------- */

    /**
     * Detect current mode by checking which asset directories exist and where.
     *
     * A project is considered LOCAL  when ANY asset dir lives inside public/.
     * A project is considered PRODUCTION when assets exist at root and NOT in public/.
     * UNKNOWN means we can't determine state (e.g. brand-new project with no assets yet).
     */
    public function detectMode(): string
    {
        $inPublic = $this->anyExistsIn('public');
        $inRoot   = $this->anyExistsIn('root');

        if ($inPublic && $inRoot) {
            return 'conflict';
        }

        if ($inPublic) {
            return 'local';
        }

        if ($inRoot) {
            return 'production';
        }

        return 'unknown';
    }

    public function isLocal(): bool
    {
        return $this->detectMode() === 'local';
    }

    public function isProduction(): bool
    {
        return $this->detectMode() === 'production';
    }

    protected function anyExistsIn(string $location): bool
    {
        foreach ($this->assetDirs as $dir) {
            $path = $location === 'public' ? public_path($dir) : base_path($dir);
            if (File::isDirectory($path) && count(File::allFiles($path)) > 0) {
                return true;
            }
        }
        return false;
    }

    /* ---------------- BACKUP ---------------- */

    protected function backupPath(string $type): string
    {
        return base_path('.env-switcher-backups/' . $type);
    }

    public function createBackup(string $type = 'previous'): void
    {
        $backupPath = $this->backupPath($type);

        if (File::isDirectory($backupPath)) {
            File::deleteDirectory($backupPath);
        }

        File::makeDirectory($backupPath, 0755, true);

        foreach ($this->assetDirs as $dir) {
            $this->copyIfExists(public_path($dir), $backupPath . '/public/' . $dir);
            $this->copyIfExists(base_path($dir),   $backupPath . '/root/'   . $dir);
        }

        // Also back up .htaccess from wherever it currently lives
        $this->copyFileIfExists(public_path('.htaccess'), $backupPath . '/public/.htaccess');
        $this->copyFileIfExists(base_path('.htaccess'),   $backupPath . '/root/.htaccess');

        $this->info("Backup created: <comment>{$type}</comment>");
    }

    protected function copyIfExists(string $from, string $to): void
    {
        if (File::isDirectory($from)) {
            File::copyDirectory($from, $to);
        }
    }

    protected function copyFileIfExists(string $from, string $to): void
    {
        if (File::isFile($from)) {
            File::ensureDirectoryExists(dirname($to));
            File::copy($from, $to);
        }
    }

    /* ---------------- SAFE MOVE WITH ATOMIC ROLLBACK ---------------- */

    /**
     * Move a directory from $from to $to.
     * If a file collision is detected, the entire operation is aborted
     * and already-moved files are rolled back before throwing.
     */
    protected function moveDirectory(string $from, string $to): void
    {
        if (!File::isDirectory($from)) {
            $this->warn("Skipping (not found): {$from}");
            return;
        }

        $files   = File::allFiles($from);
        $moved   = [];

        // Pre-flight: check for collisions before touching anything
        foreach ($files as $file) {
            $target = $to . '/' . $file->getRelativePathname();
            if (File::exists($target)) {
                throw new \RuntimeException(
                    "Cannot move — file already exists at destination:\n  {$target}\n\nRun 'php artisan env:reset' to restore a clean state, or delete the conflicting file manually."
                );
            }
        }

        // Move files one by one, tracking what we've moved for rollback
        try {
            foreach ($files as $file) {
                $relative = $file->getRelativePathname();
                $target   = $to . '/' . $relative;

                File::ensureDirectoryExists(dirname($target));
                File::move($file->getRealPath(), $target);
                $moved[] = ['from' => $file->getRealPath(), 'to' => $target];
            }

            File::deleteDirectory($from);
            $this->info("Moved: <comment>{$from}</comment> → <comment>{$to}</comment>");
        } catch (\Throwable $e) {
            // Rollback: move each successfully-moved file back
            $this->warn("Error during move — rolling back...");
            foreach (array_reverse($moved) as $pair) {
                if (File::exists($pair['to'])) {
                    File::ensureDirectoryExists(dirname($pair['from']));
                    File::move($pair['to'], $pair['from']);
                }
            }
            throw $e;
        }
    }

    /* ---------------- PRODUCTIONISE ---------------- */

    public function productionise(): void
    {
        $mode = $this->detectMode();

        if ($mode === 'production') {
            $this->warn('Already in <comment>PRODUCTION</comment> mode. Nothing to do.');
            return;
        }

        if ($mode === 'conflict') {
            throw new \RuntimeException(
                "Asset conflict detected — files exist in BOTH public/ and root.\nRun 'php artisan env:status' for details, then 'php artisan env:reset' to restore a backup."
            );
        }

        if ($mode === 'unknown') {
            throw new \RuntimeException(
                "No managed asset folders found (css, js, images, build).\nIf this is a fresh Vite project, run 'npm run build' first so public/build exists."
            );
        }

        // Create backups before touching anything
        $this->createBackup('previous');

        if (!File::isDirectory($this->backupPath('original'))) {
            $this->createBackup('original');
        }

        foreach ($this->assetDirs as $dir) {
            if (File::isDirectory(public_path($dir))) {
                $this->moveDirectory(public_path($dir), base_path($dir));
            }
        }

        if (File::isFile(public_path('.htaccess'))) {
            if (File::isFile(base_path('.htaccess'))) {
                throw new \RuntimeException(
                    "Cannot move .htaccess — file already exists at project root.\nDelete or rename the existing .htaccess at project root first."
                );
            }
            File::move(public_path('.htaccess'), base_path('.htaccess'));
            $this->info('Moved: <comment>.htaccess</comment> → project root');
        }
    }

    /* ---------------- LOCALISE ---------------- */

    public function localise(): void
    {
        $mode = $this->detectMode();

        if ($mode === 'local') {
            $this->warn('Already in <comment>LOCAL</comment> mode. Nothing to do.');
            return;
        }

        if ($mode === 'conflict') {
            throw new \RuntimeException(
                "Asset conflict detected — files exist in BOTH public/ and root.\nRun 'php artisan env:status' for details, then 'php artisan env:reset' to restore a backup."
            );
        }

        if ($mode === 'unknown') {
            throw new \RuntimeException(
                "No managed asset folders found (css, js, images, build).\nIf this is a fresh project, there is nothing to move yet."
            );
        }

        $this->createBackup('previous');

        foreach ($this->assetDirs as $dir) {
            if (File::isDirectory(base_path($dir))) {
                $this->moveDirectory(base_path($dir), public_path($dir));
            }
        }

        if (File::isFile(base_path('.htaccess'))) {
            if (File::isFile(public_path('.htaccess'))) {
                throw new \RuntimeException(
                    "Cannot move .htaccess — file already exists in public/.\nDelete or rename the existing .htaccess in public/ first."
                );
            }
            File::move(base_path('.htaccess'), public_path('.htaccess'));
            $this->info('Moved: <comment>.htaccess</comment> → public/');
        }
    }

    /* ---------------- STATUS ---------------- */

    public function status(): array
    {
        $result = [
            'mode'    => $this->detectMode(),
            'assets'  => [],
            'backups' => [],
        ];

        foreach ($this->assetDirs as $dir) {
            $inPublic = File::isDirectory(public_path($dir)) && count(File::allFiles(public_path($dir))) > 0;
            $inRoot   = File::isDirectory(base_path($dir)) && count(File::allFiles(base_path($dir))) > 0;

            $result['assets'][$dir] = [
                'in_public' => $inPublic,
                'in_root'   => $inRoot,
            ];
        }

        $backupBase = base_path('.env-switcher-backups');
        if (File::isDirectory($backupBase)) {
            foreach (File::directories($backupBase) as $dir) {
                $result['backups'][] = basename($dir);
            }
        }

        return $result;
    }

    /* ---------------- HELPERS ---------------- */

    protected function info(string $message): void
    {
        $this->command?->line("  <info>✓</info> {$message}");
    }

    protected function warn(string $message): void
    {
        $this->command?->line("  <comment>!</comment> {$message}");
    }
}