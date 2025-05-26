[![Latest Version on Packagist](https://img.shields.io/packagist/v/pepperfm/laravel-filament-starter-kit.svg?style=flat-square)](https://packagist.org/packages/pepperfm/laravel-filament-starter-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/pepperfm/laravel-filament-starter-kit.svg?style=flat-square)](https://packagist.org/packages/pepperfm/laravel-filament-starter-kit)

# [Laravel Starter Kit](https://docs.pepperfm.com/laravel-starter-kit)

A modern, developer-friendly Laravel starter kit featuring a curated selection of tools and a polished,
feature-reached admin panel — ready for production from day one.

## ✨ Features

- Laravel 12, PHP 8.3+ support
- Choose your Admin Panel during setup:
  - [Filament](https://filamentphp.com) — beautiful, customizable full-stack components
  - [Moonshine](https://moonshine-laravel.com) — comfortable, user-friendly admin panel
- Pre-installed API utilities:
    - [pepperfm/api-responder-for-laravel](https://docs.pepperfm.com/api-responder-for-laravel)
    - [spatie/laravel-data](https://github.com/spatie/laravel-data)
- Ready-to-use Swagger API docs with [l5-swagger](https://github.com/DarkaOnLine/L5-Swagger)
- Telegram bot integration via [defstudio/telegraph](https://github.com/defstudio/telegraph)
- Local development environment powered by [Laravel Sail](https://laravel.com/docs/sail)
- Pre-configured with:
    - Pest + Larastan for clean and safe testing
    - Laravel Debugbar, Ray, Pint, and Git hooks

## 📦 Installation

```bash
laravel new example-app --using=pepperfm/laravel-starter-kit
```
After creating your project, the interactive starter:setup command will run automatically, helping you choose:
- Whether to install Filament or Moonshine admin panel (or skip both)
- API support packages and Swagger docs
- Optional features like Telegram bot integration, Ray debugger, Media Library, and Permissions

You will also be prompted to configure environment variables `WWWUSER` and `WWWGROUP` for proper permissions.

✅ If you agree to automatic build and launch with Sail, the setup will:
- Install selected composer packages
- Build and start Sail containers
- Generate an app key and run post-install artisan commands specific to installed packages (e.g., `filament:install --panels` or `moonshine:install`)

❌ If you decline, run them manually:
```bash
chmod 755 ./sail
./sail up -d --build
./sail composer install
./sail artisan key:gen
./sail artisan sto:li
```

## ⚙️ Setup Command

Run manually anytime:
```bash
php artisan starter:setup
```
Interactive setup will help you customize your project features and install optional packages with their post-install steps.

## 🛠 Post-Install Commands
For installed packages, the following post-install artisan commands will run automatically (if you use auto-build):

| Package                          | Команды                                                                                 |
|----------------------------------|------------------------------------------------------------------------------------------|
| `moonshine/moonshine`           | `php artisan moonshine:install`                                                         |
| `filament/filament`             | `php artisan filament:install --panels`                                                 |
| `darkaonline/l5-swagger`        | `php artisan install:api`                                                               |
| `defstudio/telegraph`           | `php artisan vendor:publish --tag="telegraph-migrations"`<br>`php artisan migrate`      |
| `spatie/laravel-medialibrary`   | `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"`<br>`php artisan migrate` |
| `spatie/laravel-permission`     | `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`<br>`php artisan opt:cle`<br>`php artisan migrate` |


---

## 🖌 Filament Customization

Filament UI components are globally customized for improved usability and appearance:

```php
Select::configureUsing(
  fn(Select $select) => $select->searchDebounce(500)->native(false)
);

SelectFilter::configureUsing(fn(SelectFilter $filter) => $filter->native(false));

DatePicker::configureUsing(
  fn(DatePicker $picker) => $picker
      ->native(false)
      ->displayFormat('d.m.Y')
      ->suffixIcon('heroicon-m-calendar')
);

DateTimePicker::configureUsing(
  fn(DateTimePicker $picker) => $picker
      ->native(false)
      ->displayFormat('d.m.Y, H:i:s')
      ->suffixIcon('heroicon-m-calendar')
);

Column::configureUsing(
  fn(Column $column) => $column
      ->placeholder('-')
      ->searchable(isIndividual: true, isGlobal: false)
);

Section::configureUsing(
  fn(Section $section) => $section->maxWidth('xl')
);

CreateAction::configureUsing(
  fn(CreateAction $action) => $action->createAnother(false)
);
EditAction::configureUsing(
  fn(EditAction $action) => $action->iconButton()
);
ViewAction::configureUsing(
  fn(ViewAction $action) => $action->iconButton()
);
DeleteAction::configureUsing(
  fn(DeleteAction $action) => $action->iconButton()
);

Table::configureUsing(
  fn(Table $table) => $table
      ->striped()
      ->paginated([10, 25, 50])
      ->deferLoading()
);
```

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
- [Filament Docs](https://filamentphp.com)
- [Moonshine Admin](https://moonshine-php.com)
