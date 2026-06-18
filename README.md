# laravel-env-switcher

A Laravel Artisan package for developers who deploy to **shared hosting** (Hostinger, Namecheap, cPanel, etc.) where the web root is the project root — not `public/`.

On shared hosting you can't point the domain to `/public`, so everything inside `public/` — `index.php`, `.htaccess`, assets, everything — needs to live at the **project root** instead. This package moves all `public/` contents back and forth safely, including automatically patching `index.php` paths.

---

## The Problem

```
Local dev               Shared hosting
───────────            ───────────────
project/               project/
├── public/            ├── index.php     ← moved from public/
│   ├── index.php      ├── .htaccess
│   ├── .htaccess      ├── favicon.ico
│   ├── favicon.ico    ├── build/
│   ├── build/         ├── css/
│   └── css/           ├── public/       ← empty
└── ...                └── ...
```

You don't want to manually move files and patch paths every time you switch environments. This package automates that — with backups, rollback, and conflict detection.

---

## Installation

```bash
composer require mohammadsafiabdullah/laravel-env-switcher
```

Auto-discovery registers the service provider. No config file needed.

---

## Commands

| Command | What it does |
|---|---|
| `php artisan env:status` | Show current mode and what's in public/ vs root |
| `php artisan env:productionise` | Move all `public/` contents → project root (for shared hosting) |
| `php artisan env:localise` | Move everything back → `public/` (for local dev) |
| `php artisan env:backup --type=pre-deploy` | Create a named backup snapshot |
| `php artisan env:reset --to=previous` | Restore from a backup |

### Options

```bash
php artisan env:productionise --force   # skip the confirmation prompt
php artisan env:localise --force
php artisan env:reset --to=original    # restore the very first backup ever taken
php artisan env:reset --to=pre-deploy  # restore any named backup
```

---

## Typical workflow

### 1. Check current state
```bash
php artisan env:status
```

### 2. Before pushing to shared hosting
```bash
php artisan env:productionise
git add -A && git push
```

### 3. Back to local dev after pulling
```bash
php artisan env:localise
```

### 4. Something went wrong — roll back
```bash
php artisan env:reset --to=previous
# or go all the way back to the original:
php artisan env:reset --to=original
```

---

## What gets moved

**Everything** inside `public/` is moved to the project root, including:

- `index.php` (paths are automatically patched — `__DIR__.'/../'` becomes `__DIR__.'/'`)
- `.htaccess`
- `favicon.ico`, `robots.txt`
- `build/`, `css/`, `js/`, `images/` — any asset folders
- Any other files or directories you've added

**Skipped automatically:**
- Symlinks (e.g. `storage/` → `../storage/app/public`)
- `.gitignore`

---

## How mode is detected

The package uses a state file (`.env-switcher.json`) to track the current mode and which items were moved. If no state file exists, it falls back to checking where `index.php` lives.

| State | Condition |
|---|---|
| `local` | `public/index.php` exists (standard Laravel) |
| `production` | `index.php` at project root (moved from public/) |
| `conflict` | `index.php` found in BOTH locations |
| `unknown` | `index.php` not found anywhere |

---

## Backups

Every switch command automatically creates a `previous` backup before making any changes. The very first switch also saves an `original` backup. You can create additional named backups at any time:

```bash
php artisan env:backup --type=pre-deploy
```

Backups are stored in `.env-switcher-backups/` at the project root. Add this to `.gitignore`.

---

## Safety

- **Pre-flight collision check** — before moving any files, the package checks for collisions at the destination. If a file already exists, the operation is aborted before touching anything.
- **Per-item rollback** — if a move fails mid-way, already-moved items are moved back automatically.
- **Automatic path patching** — `index.php` relative paths are updated so the app works in both locations.
- **State tracking** — `.env-switcher.json` records exactly which items were moved, so `localise` knows what to move back.
- **Atomic reset** — `env:reset` copies the backup to a temp location first, then verifies it before wiping the current state. Your backup is never at risk.
- **Conflict detection** — if `index.php` ends up in both locations, the package detects this and refuses to switch until you resolve it.

---

## Add to `.gitignore`

```
.env-switcher-backups/
.env-switcher-tmp-restore-*
```

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13

---

## License

MIT © M Safi Abdullah
