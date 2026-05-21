[![Latest Version on Packagist](https://img.shields.io/packagist/v/pepperfm/laravel-starter-kit.svg?style=flat-square)](https://packagist.org/packages/pepperfm/laravel-starter-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/pepperfm/laravel-starter-kit.svg?style=flat-square)](https://packagist.org/packages/pepperfm/laravel-starter-kit)

# [Laravel Starter Kit](https://docs.pepperfm.com/laravel-starter-kit)

A modern, developer-friendly Laravel starter kit featuring a curated selection of tools and a pragmatic
setup flow — ready for production-focused application work from day one.

## ✨ Features

- Laravel 13, PHP 8.4+ support
- Optional custom admin frontend foundation during setup:
  - Inertia 3 + Vue 3 + TypeScript
  - Nuxt UI 4 + Tailwind CSS 4
  - Ziggy, Vue I18n, VueUse, Valibot
- Choose your API utility during setup:
  - [pepperfm/api-responder-for-laravel](https://docs.pepperfm.com/api-responder-for-laravel)
  - [spatie/laravel-data](https://github.com/spatie/laravel-data)
- Ready-to-use Swagger API docs with [l5-swagger](https://github.com/DarkaOnLine/L5-Swagger)
- Telegram bot integration via [defstudio/telegraph](https://github.com/defstudio/telegraph)
- Local development environment powered by [Laravel Sail](https://laravel.com/docs/sail)
- Pre-configured with:
    - Pest + Larastan for clean and safe testing
    - Laravel Debugbar, Ray, and Pint

## 📦 Installation

```bash
laravel new example-app --using=pepperfm/laravel-starter-kit --database=pgsql
```
The `--database=pgsql` flag is important: Laravel Installer defaults custom starter kits to SQLite when no database driver is specified.
When using Sail, choose the Sail runtime during setup so containers are available for Laravel Installer's final migration step.

After creating your project, the interactive starter:setup command will run automatically, helping you choose:
- Whether setup commands should run on the host machine, through Sail, or auto-detect a running Sail container
- Whether to publish the custom admin frontend foundation
- API support packages and Swagger docs
- Optional features like Telegram bot integration, Ray debugger, Media Library, and Permissions

You will also be prompted to configure environment variables `WWWUSER` and `WWWGROUP` for proper permissions. The setup command also normalizes the PostgreSQL `DB_*` variables so they are active after `laravel new`.

If you choose Sail and the app container is not running yet, setup can start Sail with `./sail up -d --build` before installing selected packages.

During setup, the selected command runtime controls:
- Installing selected composer packages
- Publishing selected frontend preset files and installing frontend dependencies
- Running post-install artisan commands specific to installed packages

If you skip Sail during setup, you can start it later:
```bash
chmod 755 ./sail
./sail up -d --build
```

## ⚙️ Setup Command

Run manually anytime:
```bash
php artisan starter:setup
```
Interactive setup will help you customize your project features and install optional packages with their post-install steps.

## 🛠 Post-Install Commands
For installed packages, the following post-install artisan commands will run automatically unless `--no-post` is used:

| Package                          | Команды                                                                                 |
|----------------------------------|------------------------------------------------------------------------------------------|
| `darkaonline/l5-swagger`        | `php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"`<br>`php artisan l5-swagger:generate` |
| `defstudio/telegraph`           | `php artisan vendor:publish --tag="telegraph-migrations"`<br>`php artisan migrate`      |
| `spatie/laravel-medialibrary`   | `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"`<br>`php artisan migrate` |
| `spatie/laravel-permission`     | `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`<br>`php artisan optimize:clear`<br>`php artisan migrate` |

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
