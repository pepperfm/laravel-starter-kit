# Базовые правила проекта

> Автоматически собранные правила по текущему коду. При изменении архитектуры или setup flow обновляйте этот файл.

## Naming Conventions

- Files: Laravel/PHP классы в `PascalCase.php`, миграции в `snake_case`, frontend entry files в `camelCase` или стандартных именах Vite.
- Variables: PHP переменные и свойства в `camelCase`.
- Functions: методы классов в `camelCase`; global helpers в `camelCase`.
- Classes: PHP классы, exceptions, providers и commands в `PascalCase`.
- Namespaces: `App\...` по PSR-4, тесты в `Tests\...`.

## Module Structure

- Базовый Laravel skeleton сохраняет стандартные директории `app/`, `routes/`, `config/`, `database/`, `resources/`, `tests/`.
- Новую доменную функциональность добавлять через module/application service boundaries, не раздувая controllers и setup-command.
- `app/Console/Commands/SetupCommand.php` отвечает только за интерактивную установку starter kit опций.
- Admin panel код и пакеты Filament/Moonshine не считать целевым модулем: новая админка будет отдельным решением.

## Error Handling

- Для API-запросов использовать централизованный JSON rendering через `app/Bootstrap/WithExceptions.php`.
- Доменные ошибки оформлять typed exceptions в `App\Exceptions`.
- Не раскрывать внутренние stack traces в публичных responses; детали ошибок отправлять в logger.

## Logging

- Использовать Laravel `logger()` или стандартные Laravel logging facilities.
- Не использовать `dd`, `dump`, `ray`, `env` в production-коде; это уже закреплено `tests/Feature/ArchTest.php`.
- Логи ошибок должны содержать полезный контекст без secrets и персональных данных без необходимости.

## Testing

- Использовать Pest для feature/architecture tests.
- Новые публичные flows покрывать feature tests; чистые доменные правила покрывать unit tests, если они появятся.
- Перед commit запускать Pint/Larastan/Pest через существующие project commands или Sail wrappers.
- Учитывать dev tooling из зависимостей: Laravel Boost, Laravel Brain, PepperFM AI Guidelines, Pest Arch/mutation/profanity plugins.

## Admin Panels

- Filament и Moonshine исключены из текущего AI context как целевые админки.
- Не добавлять новые планы, architecture decisions или rules, завязанные на Filament/Moonshine, пока не будет отдельной задачи на новую admin area.
