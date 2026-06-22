# AGENTS.md

> Карта проекта для AI agents и разработчиков. Обновляйте файл при значимых изменениях структуры.

## Project Overview

Laravel starter kit для PepperFM с интерактивной setup-командой, Sail окружением и AI/dev quality tooling. Filament/Moonshine не входят в setup flow. Для новой админки используется optional custom frontend foundation на Inertia + Vue + Nuxt UI.

## Tech Stack

- **Programming language:** PHP 8.4+
- **Framework:** Laravel 13
- **Database:** PostgreSQL 17 через Sail
- **Cache/queue:** Redis через Sail, стандартные Laravel config files
- **Frontend:** Blade + Vite assets by default; optional Inertia 3 + Vue 3 + TypeScript + Nuxt UI 4 admin preset
- **Tests:** Pest 4, Pest Laravel 4, Larastan, Pint, Laravel PAO
- **AI/dev tooling:** Laravel Boost, Laravel Brain, Laravel PAO, PepperFM AI Guidelines

## Project Structure

```text
app/
  Bootstrap/          # Laravel bootstrap customizers for exceptions and middleware
  Console/Commands/   # Starter kit interactive setup command
  Setup/              # Setup installers for optional starter-kit presets
  Exceptions/         # Typed application/http exceptions
  Http/Controllers/   # Standard Laravel controllers
  Models/             # Eloquent models
  Providers/          # Laravel service providers
bootstrap/            # Laravel bootstrap files
config/               # Laravel configuration
database/             # Migrations, factories, seeders
docker/               # Sail Docker image and PostgreSQL test database setup
resources/            # Blade views, CSS, JS
routes/               # Web and console routes
stubs/                # Publishable starter-kit presets and Laravel stubs
tests/                # Pest feature and architecture tests
.ai-factory/          # AI Factory project context
.codex/               # Project-local Codex skills and config
```

## Key Entry Points

| File | Purpose |
| --- | --- |
| `artisan` | Laravel CLI entry point |
| `app/Console/Commands/SetupCommand.php` | Интерактивная настройка `.env`, host/Sail runtime и установка опциональных пакетов |
| `app/Setup/AdminPanelFrontendInstaller.php` | Публикация optional Inertia/Vue/Nuxt UI admin frontend preset |
| `app/Setup/StarterKitPreset.php` | Preset-наборы `api`, `admin`, `observability`, `full` для setup-команды |
| `app/Setup/StarterKitPackageRegistry.php` | Метаданные optional packages, dev/runtime grouping и post-install команды |
| `app/Setup/StarterKitInstallationSummary.php` | Console summary перед запуском Composer/frontend/post-install шагов |
| `routes/web.php` | Web route definitions |
| `app/Bootstrap/WithExceptions.php` | JSON error rendering для API-запросов |
| `app/Bootstrap/WithMiddleware.php` | Guest redirect на `/panel` для новой admin area |
| `app/Providers/AppServiceProvider.php` | Общие Laravel boot rules |
| `docker-compose.yml` | Sail services: app, PostgreSQL, Redis |
| `vite.config.js` | Vite/Laravel asset pipeline |
| `tests/Feature/ArchTest.php` | Architecture expectations для PHP-кода |
| `tests/Feature/SetupCommandTest.php` | Проверка setup env normalization |

## Documentation

| Document | Path | Description |
| --- | --- | --- |
| README | `README.md` | Публичное описание starter kit и установки |
| AI Factory description | `.ai-factory/DESCRIPTION.md` | Текущий AI context проекта |
| Architecture | `.ai-factory/ARCHITECTURE.md` | Архитектурные правила для будущих изменений |
| Base rules | `.ai-factory/rules/base.md` | Автоопределенные conventions и ограничения |

## AI Context Files

| File | Purpose |
| --- | --- |
| `AGENTS.md` | Быстрая карта проекта для AI agents |
| `.ai-factory/config.yaml` | Настройки языка, путей, workflow и git для AI Factory |
| `.ai-factory/DESCRIPTION.md` | Описание проекта, stack и исключения |
| `.ai-factory/ARCHITECTURE.md` | Архитектурный ориентир для планов и реализации |
| `.ai-factory/rules/base.md` | Базовые project rules |

## Agent Rules

- Команды с shell control operators разбивать на отдельные шаги.
  - Неверно: `git checkout master && git pull`
  - Верно: сначала `git checkout master`, затем `git pull origin master`
- Не считать Filament/Moonshine целевой архитектурой или обязательной dependency.
- Не писать бизнес-код во время setup-контекста; для изменений использовать `$aif-plan`, затем `$aif-implement`.
