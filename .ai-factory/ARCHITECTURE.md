# Architecture: Structured Modules (Technical Layers)

## Overview

Проект остается Laravel starter kit и должен сохранять знакомые Laravel conventions. Для будущей функциональности используем Structured Modules: легкую модульную архитектуру с controller/application service/repository boundaries там, где появляется бизнес-логика.

Выбор намеренно прагматичный: текущий код маленький, но starter kit будет расти за счет опциональных пакетов, API support и новой admin area. Полная Explicit Architecture пока избыточна, а обычный flat Laravel без границ быстро приведет к перегруженным controllers, providers и setup-командам.

## Decision Rationale

- **Project type:** Laravel starter kit с интерактивной установкой опциональных компонентов.
- **Tech stack:** PHP 8.4+, Laravel 13, Sail, PostgreSQL, Redis, Pest 4/Larastan/Pint.
- **Key factor:** нужна структура для роста без преждевременного DDD/hexagonal формализма.
- **Admin note:** Filament и Moonshine исключены из целевого контекста; новая admin area должна проектироваться отдельным модулем после уточнения требований.

## Folder Structure

```text
app/
  Modules/
    <ModuleName>/
      Http/
        Controllers/
        Requests/
        Resources/
      Application/
        Services/
        Data/
      Domain/
        Models/
        Enums/
        Exceptions/
      Infrastructure/
        Repositories/
        Integrations/
  Bootstrap/
  Console/
    Commands/
  Exceptions/
  Http/
    Controllers/
  Models/
  Providers/
routes/
  web.php
  api.php              # when API routes are introduced
tests/
  Feature/
  Unit/
```

Стандартные Laravel directories сохраняются. `app/Modules` добавляется только когда появляется реальная доменная область, которой тесно в стандартном skeleton.

## Dependency Rules

- Разрешено: `Controllers -> Application Services -> Repositories/Integrations`.
- Разрешено: `Application` вызывает domain methods и координирует use cases.
- Разрешено: `Infrastructure` реализует доступ к storage/external systems.
- Запрещено: controllers напрямую пишут сложные Eloquent queries или вызывают внешние API.
- Запрещено: domain objects зависят от controllers, requests, service providers или конкретной admin panel.
- Запрещено: один module импортирует internals другого module без публичного application-level API.

## Layer/Module Communication

- HTTP слой принимает request, валидирует input и вызывает application service.
- Application service управляет use case, транзакциями и orchestration.
- Domain слой держит инварианты, value decisions и typed exceptions.
- Infrastructure слой скрывает детали persistence/external integrations.
- Cross-module взаимодействие идет через application services, DTO/data objects или events, а не через прямой доступ к чужим internals.

## Key Principles

1. Laravel conventions first: использовать framework там, где он дает простое и читаемое решение.
2. Business rules не должны жить в setup-command, service provider или Blade view.
3. Admin area является отдельной delivery surface, а не центром доменной модели.
4. Optional packages подключаются через явные adapters/configuration, чтобы starter kit оставался гибким.
5. Tests и AI/dev tooling закрепляют архитектурные ожидания: strict types, отсутствие debug helpers, понятные boundaries, Larastan/Pint/Pest checks.

## Code Examples

### Controller delegates to Application Service

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\Services\CreateProductService;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use Illuminate\Http\JsonResponse;

final class ProductController extends Controller
{
    public function store(StoreProductRequest $request, CreateProductService $service): JsonResponse
    {
        $product = $service->handle($request->validated());

        return response()->json([
            'data' => $product,
        ], JsonResponse::HTTP_CREATED);
    }
}
```

### Application Service owns orchestration

```php
<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Infrastructure\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

final readonly class CreateProductService
{
    public function __construct(
        private ProductRepository $products,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): Product
    {
        return DB::transaction(function () use ($payload): Product {
            $product = Product::fromSetupPayload($payload);

            return $this->products->store($product);
        });
    }
}
```

## Anti-Patterns

- Не строить новые features вокруг Filament/Moonshine, пока admin area не перепроектирована.
- Не превращать `AppServiceProvider` в место для бизнес-правил.
- Не расширять `SetupCommand` логикой, которая должна быть отдельным service/use case.
- Не смешивать package installation decisions с runtime domain behavior.
- Не добавлять shared helpers без сильной причины; prefer typed services/classes.
- Не обходить существующие AI/dev guidelines (`pepperfm/ai-guidelines`, Laravel Boost/Brain), если они задают более конкретные project rules.
