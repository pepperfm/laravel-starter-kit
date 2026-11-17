[![Latest Version on Packagist](https://img.shields.io/packagist/v/pepperfm/laravel-starter-kit.svg?style=flat-square)](https://packagist.org/packages/pepperfm/laravel-starter-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/pepperfm/laravel-starter-kit.svg?style=flat-square)](https://packagist.org/packages/pepperfm/laravel-starter-kit)

# [Laravel Starter Kit](https://docs.pepperfm.com/laravel-starter-kit)

A modern, developer-friendly Laravel starter kit featuring a curated selection of tools and a pragmatic
setup flow — ready for production-focused application work from day one.

## ✨ Features

- Laravel 13, PHP 8.5+ requirement
- Optional custom admin frontend foundation during setup:
  - Inertia 3 + Vue 3 + TypeScript
  - Nuxt UI 4 + Tailwind CSS 4 (based on Nuxt UI Dashboard primitives, without requiring Nuxt)
  - Ziggy, Vue I18n, VueUse, Valibot
- Independently choose API components during setup (none are required together):
  - [Laravel Sanctum](https://laravel.com/docs/sanctum) for token authentication and API routes
  - [Swagger Nuxt UI](https://docs.pepperfm.com/swagger-nuxt-ui-for-laravel) for API documentation
  - [spatie/laravel-data](https://github.com/spatie/laravel-data) for DTOs
  - [pepperfm/api-responder-for-laravel](https://docs.pepperfm.com/api-responder-for-laravel) for response helpers
- Optional observability tools during setup:
  - [OPcodes Log Viewer](https://log-viewer.opcodes.io/)
  - [Laravel Horizon](https://laravel.com/docs/horizon)
  - [Laravel Telescope](https://laravel.com/docs/telescope)
  - [Laravel Pulse](https://laravel.com/docs/pulse)
- Telegram bot integration via [defstudio/telegraph](https://github.com/defstudio/telegraph)
- Local development environment powered by [Laravel Sail](https://laravel.com/docs/sail)
- UUID primary keys for the base user model
- Pre-configured with:
    - Pest + Larastan + Laravel PAO for clean and agent-friendly testing
    - Laravel Debugbar, Ray, and Pint

## 📦 Installation

Requires **PHP 8.5**, Composer, and the [Laravel Installer](https://github.com/laravel/installer).
For Sail development, also install Docker Compose. The host must have the PostgreSQL PDO
extension if you use the command below: Laravel Installer runs its own migrations on the host.

~~~bash
laravel new example-app --using=pepperfm/laravel-starter-kit --database=pgsql
~~~

The installer creates the application and initializes its key before invoking the interactive
`starter:setup --installer-hook` hook. In the hook you can select the host or Sail runtime,
admin frontend, independent API components, and optional observability packages. The plan is shown
before any project files are modified.

**During that hook:**

- Optional Composer packages are installed on the **host**, before any Docker build; packages
  already present in `composer.json` are not required again.
- Preset files and vendor migrations are published, without running migrations yet.
- `.env` is prepared for host connections, while `config/database.php` resolves loopback
  PostgreSQL/Redis to service names when the application runs under Sail. Custom remote
  hostnames/URLs and existing non-placeholder credentials are preserved.
- If Sail is selected, Docker Compose starts after Composer and waits for service health.
  Its full build output is streamed to the terminal.

**After that hook, Laravel Installer resumes:** it configures `APP_URL` and the selected
database, runs `artisan migrate` on the host (for the explicit `--database=pgsql` flag),
then chooses and installs/builds frontend dependencies. The starter hook deliberately does
**not** run those stages a second time. Installer respects `--npm`, `--pnpm`,
`--bun`, `--yarn` and `--no-node`. With no explicit package manager,
its current lockfile detection typically chooses Bun for this repository.

> **Installer limitation:** `laravel new ... --database=pgsql` does not expose a
> migration opt-out. If you need to review/decline migrations or cannot migrate from the
> host, create the project with Composer and run `starter:setup` manually instead.
> The starter's `--no-migrate` flag applies only to manual setup. See
> [the lifecycle analysis](docs/INSTALLER-LIFECYCLE.md) for source references and trade-offs.

The admin preset replaces only pristine Laravel skeleton `package.json`,
`vite.config.js` and `resources/css/app.css` (plus missing files). Customized files are never
overwritten. Re-running setup preserves existing preset files and skips already-installed Composer
requirements. If installation stops partway through, fix the failure and run setup again; it does
not implement transactional rollback.

The panel uses Laravel session authentication. Guests visiting `/panel` are redirected
to `/panel/login`; failed attempts are rate-limited and logout invalidates the session.
There is no public registration. After migrations, create the first panel user with
`php artisan panel:user`. **Any authenticated application user can currently access the
panel**; use policies/roles for production authorization.

## ⚙️ Manual setup

~~~bash
php artisan starter:setup
php artisan starter:setup --preset=api
php artisan starter:setup --preset=admin
php artisan starter:setup --preset=observability
php artisan starter:setup --preset=full

# Prevent post-install Artisan commands, migrations, and Node installation:
php artisan starter:setup --preset=full --no-post --no-migrate --skip-node
~~~

Manual setup asks separately whether to run pending migrations and install frontend
dependencies. Use `--no-migrate` or `--skip-node` to suppress those steps. A manually
selected Sail runtime still requires host Composer, but the remaining Artisan and optional
frontend commands run inside Sail once healthy.

| Preset | Includes |
| --- | --- |
| `api` | Sanctum, Swagger documentation and Laravel Data |
| `admin` | Inertia + Vue + Nuxt UI panel |
| `observability` | Log Viewer, Horizon, Pulse, Telescope (dev dependency) |
| `full` | Admin + API + observability |

Sanctum is installed once using Composer; `routes/api.php` and the `HasApiTokens` trait are
set up without invoking `install:api`, which would run Composer internally.
Horizon, Telescope, Pulse, Telegraph, MediaLibrary and Permissions publish their required
assets/migrations before the migration stage.

For a more detailed execution map, failure behavior, and reproducible smoke tests,
read [Installer Lifecycle](docs/INSTALLER-LIFECYCLE.md).

---

## 🧪 Testing & Quality

```bash
make pint        # Show unstaged files with codestyle issues
make pint-hard   # Fix codestyle issues automatically
make stan        # Run Larastan (static analysis)
make test        # Run all tests via Pest
```

---

## 📚 Useful Links

- [API Responder Documentation](https://docs.pepperfm.com/api-responder-for-laravel)
- [Spatie Laravel Data](https://github.com/spatie/laravel-data)
