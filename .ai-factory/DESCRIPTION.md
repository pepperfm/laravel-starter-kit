# Описание проекта

## Обзор

`pepperfm/laravel-starter-kit` — Laravel starter kit для быстрого запуска PHP-приложений с готовой локальной средой, AI/dev quality tooling и интерактивной установкой опциональных пакетов.

Текущий контекст проекта намеренно не строится вокруг прежних admin panel вариантов. Filament и Moonshine исключены из setup-команды и README. Новая админка начинается как optional frontend foundation на Inertia + Vue + Nuxt UI.

## Core Features

- Интерактивная команда `php artisan starter:setup` для настройки `.env`, выбора host/Sail runtime, optional admin frontend preset, API-поддержки и дополнительных пакетов.
- При установке через `laravel new --using=pepperfm/laravel-starter-kit` нужно передавать `--database=pgsql`, иначе Laravel Installer для custom starter kits выбирает SQLite после Composer scripts.
- Laravel Sail окружение с PostgreSQL 17 и Redis.
- Базовый web entry point через `routes/web.php` и `resources/views/welcome.blade.php`.
- Подготовленные quality tools: Pest, Larastan, Pint, Laravel PAO, Laravel Boost, Laravel Brain и PepperFM AI Guidelines.
- Опциональные интеграции через setup-команду: custom admin frontend foundation, Swagger/L5 Swagger, Spatie Laravel Data, API responder, Telegraph, MediaLibrary, Permissions, Ray.

## Исключения из целевого контекста

- Не использовать Filament как архитектурную основу для новых планов.
- Не использовать Moonshine как архитектурную основу для новых планов.
- Единый целевой URL новой admin area — `/panel`.
- Новую admin area проектировать отдельным планом после уточнения требований.

## Tech Stack

- **Programming language:** PHP 8.4+
- **Framework:** Laravel 13
- **Primary keys:** базовая `App\Models\User` использует UUID primary key через `HasUuids`
- **Frontend assets:** plain Blade/CSS/JS skeleton by default; optional admin preset publishes Inertia 3, Vue 3, TypeScript, Nuxt UI 4, Tailwind CSS 4, Ziggy, Vue I18n, VueUse and Valibot files
- **Database:** PostgreSQL 17 в Sail, SQLite может использоваться стандартными Laravel сценариями
- **Cache/queue support:** Redis в Sail, стандартные Laravel queue/cache конфиги
- **Testing:** Pest 4 + Pest Laravel 4, Pest Arch, mutation/profanity plugins
- **Static analysis:** Larastan 3
- **Formatting:** Laravel Pint
- **AI/dev tooling:** Laravel Boost, Laravel Brain, Laravel PAO, PepperFM AI Guidelines, Laravel MCP transitive package
- **Debug tooling:** Fruitcake Laravel Debugbar, Spatie Ray
- **Local environment:** Laravel Sail, Docker Compose

## Текущая структура

- `app/Console/Commands/SetupCommand.php` содержит интерактивную настройку `.env`, выбор host/Sail runtime, установку опциональных пакетов и optional admin frontend preset.
- `app/Setup/AdminPanelFrontendInstaller.php` публикует admin frontend stubs и выбирает host/Sail команду установки frontend dependencies.
- `app/Bootstrap/WithExceptions.php` централизует JSON error responses для API-запросов.
- `app/Bootstrap/WithMiddleware.php` перенаправляет гостей на `/panel`.
- `app/Models/User.php` — базовая Eloquent user model.
- `resources/` содержит стартовые Blade/CSS/JS ассеты.
- `tests/Feature/ArchTest.php` задает базовые architecture expectations для strict types, enum/interfaces namespaces и запрета debug helpers.

## Architecture

Подробные архитектурные правила находятся в `.ai-factory/ARCHITECTURE.md`.

**Pattern:** Structured Modules (Technical Layers) поверх стандартных Laravel conventions.

## Non-Functional Requirements

- **Error handling:** API-запросы должны возвращать структурированные JSON responses; web-запросы остаются в стандартном Laravel flow.
- **Logging:** использовать Laravel logger, не оставлять debug helpers (`dd`, `dump`, `ray`, `env`) в прикладном коде.
- **Security:** не полагаться на admin panel package как на boundary безопасности; авторизация и permissions должны проектироваться явно.
- **Code style:** `declare(strict_types=1);`, типизированные методы, Pint/Larastan/Pest перед merge.
- **Operations:** локальные сервисы поднимать через Sail/Docker Compose; env credentials не хранить в репозитории.
