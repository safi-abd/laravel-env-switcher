# laravel-env-switcher

A Laravel Artisan package for developers who deploy to **shared hosting** (Hostinger, Namecheap, cPanel, etc.) where the web root is the project root — not `public/`.

On shared hosting you can't point the domain to `/public`, so your compiled assets (`css/`, `js/`, `images/`, `build/`) need to live at the **project root** instead. But locally, they live inside `public/` like a normal Laravel project. This package moves them back and forth safely.

---

## The Problem

```
Local dev               Shared hosting
───────────            ───────────────
project/               project/
├── public/            ├── css/          ← assets at root
│   ├── css/           ├── js/
│   ├── js/            ├── build/
│   └── build/         ├── public/
└── ...                └── ...
```

You don't want to manually move folders every time you switch environments. This package automates that — with backups, rollback, and conflict detection.

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
| `php artisan env:status` | Show current mode and state of all asset folders |
| `php artisan env:productionise` | Move assets from `public/` → project root (for shared hosting) |
| `php artisan env:localise` | Move assets from project root → `public/` (for local dev) |
| `php artisan env:backup --type=pre-deploy` | Create a named backup snapshot |
| `php artisan env:reset --to=previous` | Restore from a backup |

### Options

```bash
php artisan env:productionise --force   # skip the confirmation prompt
php artisan env:localise --force
php artisan env:reset --to=original    # restore the very first backup ever taken
php artisan env:reset --to=manual      # restore a manually-created backup
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

- `css/`
- `js/`
- `images/`
- `build/` (Vite output)
- `.htaccess` (moved alongside assets)

---

## Backups

Every switch command automatically creates a `previous` backup before making any changes. The very first switch also saves an `original` backup. You can create additional named backups at any time:

```bash
php artisan env:backup --type=pre-deploy
```

Backups are stored in `.env-switcher-backups/` at the project root. Add this to `.gitignore`.

---

## How mode is detected

The package checks whether any of the managed directories (`css`, `js`, `images`, `build`) contain files in `public/` vs the project root. This means it works with any asset pipeline — Vite, Mix, or manual.

| State | Condition |
|---|---|
| `local` | At least one asset dir found in `public/` |
| `production` | At least one asset dir found at root, none in `public/` |
| `conflict` | Asset dirs found in BOTH locations |
| `unknown` | No managed asset dirs found anywhere |

---

## Safety

- **Pre-flight collision check** — before moving any files, the package checks for collisions at the destination. If a file already exists, the operation is aborted before touching anything.
- **Per-file rollback** — if a move fails mid-way, already-moved files are moved back automatically.
- **Atomic reset** — `env:reset` copies the backup to a temp location first, then verifies it before wiping the current state. Your backup is never at risk.
- **Conflict detection** — if assets end up in both locations (e.g. after a failed operation or manual edits), the package detects this and refuses to switch until you resolve it.

---

## Add `.env-switcher-backups/` to `.gitignore`

```
.env-switcher-backups/
.env-switcher-tmp-restore-*
```

---

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

---

## License

MIT © M Safi Abdullah
