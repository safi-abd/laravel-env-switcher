<?php

namespace SafiCodes\HostKit\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class HostKit
{
    protected ?Command $command = null;

    protected array $skipItems = ['.gitignore'];

    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    /* ---------------- STATE FILE ---------------- */

    protected function statePath(): string
    {
        return base_path('.hostkit.json');
    }

    public function readState(): ?array
    {
        if (File::isFile($this->statePath())) {
            return json_decode(File::get($this->statePath()), true);
        }
        return null;
    }

    protected function writeState(string $mode, array $moved = []): void
    {
        File::put($this->statePath(), json_encode([
            'mode' => $mode,
            'moved' => $moved,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function deleteState(): void
    {
        if (File::isFile($this->statePath())) {
            File::delete($this->statePath());
        }
    }

    /* ---------------- MODE DETECTION ---------------- */

    public function detectMode(): string
    {
        $state = $this->readState();
        if ($state && isset($state['mode'])) {
            return $state['mode'];
        }

        $inPublic = File::isFile(public_path('index.php'));
        $inRoot = File::isFile(base_path('index.php'));

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

    /* ---------------- PUBLIC CONTENTS ---------------- */

    public function getPublicItems(): array
    {
        $items = [];
        $publicPath = public_path();

        if (!File::isDirectory($publicPath)) {
            return $items;
        }

        foreach (File::files($publicPath, true) as $file) {
            $name = $file->getFilename();
            if (!in_array($name, $this->skipItems) && !is_link($file->getPathname())) {
                $items[] = $name;
            }
        }

        foreach (File::directories($publicPath) as $dir) {
            $name = basename($dir);
            if (!in_array($name, $this->skipItems) && !is_link($dir)) {
                $items[] = $name;
            }
        }

        return $items;
    }

    public function getMovedItems(): array
    {
        $state = $this->readState();
        return $state['moved'] ?? [];
    }

    /* ---------------- BACKUP ---------------- */

    protected function backupPath(string $type): string
    {
        return base_path('.hostkit-backups/' . $type);
    }

    public function createBackup(string $type = 'previous'): void
    {
        $backupPath = $this->backupPath($type);

        if (File::isDirectory($backupPath)) {
            File::deleteDirectory($backupPath);
        }

        File::makeDirectory($backupPath, 0755, true);

        $publicPath = public_path();
        if (File::isDirectory($publicPath)) {
            $backupPublic = $backupPath . '/public';
            File::makeDirectory($backupPublic, 0755, true);

            foreach (File::files($publicPath, true) as $file) {
                if (!is_link($file->getPathname())) {
                    File::copy($file->getRealPath(), $backupPublic . '/' . $file->getFilename());
                }
            }

            foreach (File::directories($publicPath) as $dir) {
                if (!is_link($dir)) {
                    File::copyDirectory($dir, $backupPublic . '/' . basename($dir));
                }
            }
        }

        $movedItems = $this->getMovedItems();
        if (!empty($movedItems)) {
            $backupRoot = $backupPath . '/root';
            File::makeDirectory($backupRoot, 0755, true);

            foreach ($movedItems as $item) {
                $source = base_path($item);
                $dest = $backupRoot . '/' . $item;

                if (File::isDirectory($source) && !is_link($source)) {
                    File::copyDirectory($source, $dest);
                } elseif (File::isFile($source)) {
                    File::copy($source, $dest);
                }
            }
        }

        if (File::isFile($this->statePath())) {
            File::copy($this->statePath(), $backupPath . '/.hostkit.json');
        }

        $this->info("Backup created: <comment>{$type}</comment>");
    }

    /* ---------------- MOVE WITH ROLLBACK ---------------- */

    protected function moveItems(array $items, string $fromBase, string $toBase): void
    {
        $moved = [];

        foreach ($items as $item) {
            $target = $toBase . '/' . $item;
            if (File::exists($target) || File::isDirectory($target)) {
                throw new \RuntimeException(
                    "Cannot move — '{$item}' already exists at destination:\n  {$target}\n\nResolve the conflict manually or run 'php artisan env:reset'."
                );
            }
        }

        try {
            foreach ($items as $item) {
                $source = $fromBase . '/' . $item;
                $target = $toBase . '/' . $item;

                if (!File::exists($source) && !File::isDirectory($source)) {
                    continue;
                }

                if (File::isDirectory($source)) {
                    File::copyDirectory($source, $target);
                    File::deleteDirectory($source);
                } else {
                    File::ensureDirectoryExists(dirname($target));
                    File::move($source, $target);
                }

                $moved[] = $item;
                $this->info("Moved: <comment>{$item}</comment>");
            }
        } catch (\Throwable $e) {
            $this->warn("Error during move — rolling back...");
            foreach (array_reverse($moved) as $item) {
                $source = $fromBase . '/' . $item;
                $target = $toBase . '/' . $item;

                try {
                    if (File::isDirectory($target)) {
                        File::copyDirectory($target, $source);
                        File::deleteDirectory($target);
                    } elseif (File::isFile($target)) {
                        File::ensureDirectoryExists(dirname($source));
                        File::move($target, $source);
                    }
                } catch (\Throwable $rollbackError) {
                    // Best-effort rollback
                }
            }
            throw $e;
        }
    }

    /* ---------------- INDEX.PHP PATH PATCHING ---------------- */

    protected function patchIndexPhp(string $path, string $direction): void
    {
        if (!File::isFile($path)) {
            return;
        }

        $content = File::get($path);

        if ($direction === 'production') {
            $content = str_replace("__DIR__.'/../", "__DIR__.'/", $content);
            $content = str_replace('__DIR__."/../', '__DIR__."/', $content);
        } else {
            $content = str_replace("__DIR__.'/", "__DIR__.'/../", $content);
            $content = str_replace('__DIR__."/', '__DIR__."/../', $content);
        }

        File::put($path, $content);
    }

    /* ---------------- HTACCESS SECURITY ---------------- */

    protected function securityBlock(): string
    {
        return <<<'HTACCESS'

# BEGIN HOSTKIT SECURITY
# Protect sensitive files and directories when public/ contents are at project root
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Sensitive files
    RewriteRule ^\.env$ - [F,L]
    RewriteRule ^\.env\..+$ - [F,L]
    RewriteRule ^artisan$ - [F,L]
    RewriteRule ^composer\.(json|lock)$ - [F,L]
    RewriteRule ^package(-lock)?\.json$ - [F,L]
    RewriteRule ^phpunit\.xml(\.dist)?$ - [F,L]
    RewriteRule ^webpack\.mix\.js$ - [F,L]
    RewriteRule ^vite\.config\.(js|ts)$ - [F,L]
    RewriteRule ^\.hostkit\.json$ - [F,L]

    # Sensitive directories
    RewriteRule ^app/ - [F,L]
    RewriteRule ^bootstrap/ - [F,L]
    RewriteRule ^config/ - [F,L]
    RewriteRule ^database/ - [F,L]
    RewriteRule ^lang/ - [F,L]
    RewriteRule ^resources/ - [F,L]
    RewriteRule ^routes/ - [F,L]
    RewriteRule ^storage/ - [F,L]
    RewriteRule ^tests/ - [F,L]
    RewriteRule ^vendor/ - [F,L]
    RewriteRule ^\.hostkit-backups/ - [F,L]
</IfModule>
# END HOSTKIT SECURITY
HTACCESS;
    }

    protected function addSecurityRules(): void
    {
        $htaccess = base_path('.htaccess');

        if (!File::isFile($htaccess)) {
            return;
        }

        $content = File::get($htaccess);

        if (str_contains($content, '# BEGIN HOSTKIT SECURITY')) {
            return;
        }

        File::append($htaccess, $this->securityBlock());
        $this->info("Added security rules to <comment>.htaccess</comment>");
    }

    protected function removeSecurityRules(): void
    {
        $htaccess = base_path('.htaccess');

        if (!File::isFile($htaccess)) {
            return;
        }

        $content = File::get($htaccess);

        $pattern = '/\n?# BEGIN HOSTKIT SECURITY.*?# END HOSTKIT SECURITY/s';
        $cleaned = preg_replace($pattern, '', $content);

        if ($cleaned !== $content) {
            File::put($htaccess, $cleaned);
            $this->info("Removed security rules from <comment>.htaccess</comment>");
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
                "Conflict detected — index.php exists in BOTH public/ and project root.\nRun 'php artisan env:status' for details, then 'php artisan env:reset' to restore a backup."
            );
        }

        if ($mode === 'unknown') {
            throw new \RuntimeException(
                "Cannot determine current mode — public/index.php not found.\nEnsure you have a standard Laravel public/ directory."
            );
        }

        $items = $this->getPublicItems();

        if (empty($items)) {
            throw new \RuntimeException("Nothing to move — public/ directory is empty.");
        }

        $this->createBackup('previous');

        if (!File::isDirectory($this->backupPath('original'))) {
            $this->createBackup('original');
        }

        $this->moveItems($items, public_path(), base_path());

        $this->patchIndexPhp(base_path('index.php'), 'production');
        $this->info("Patched <comment>index.php</comment> paths for project root");

        $this->addSecurityRules();

        $this->writeState('production', $items);
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
                "Conflict detected — index.php exists in BOTH public/ and project root.\nRun 'php artisan env:status' for details, then 'php artisan env:reset' to restore a backup."
            );
        }

        if ($mode === 'unknown') {
            throw new \RuntimeException(
                "Cannot determine current mode.\nIf this is a fresh project, there is nothing to move yet."
            );
        }

        $movedItems = $this->getMovedItems();

        if (empty($movedItems)) {
            throw new \RuntimeException(
                "No record of moved items found in .hostkit.json.\nRun 'php artisan env:reset' to restore from a backup."
            );
        }

        $this->createBackup('previous');

        $this->removeSecurityRules();

        $this->patchIndexPhp(base_path('index.php'), 'local');
        $this->info("Patched <comment>index.php</comment> paths for public/");

        $this->moveItems($movedItems, base_path(), public_path());

        $this->deleteState();
    }

    /* ---------------- STATUS ---------------- */

    public function status(): array
    {
        $result = [
            'mode' => $this->detectMode(),
            'public_items' => $this->getPublicItems(),
            'moved_items' => $this->getMovedItems(),
            'backups' => [],
        ];

        $backupBase = base_path('.hostkit-backups');
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
