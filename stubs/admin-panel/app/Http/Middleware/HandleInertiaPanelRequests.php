<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

final class HandleInertiaPanelRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'ziggy' => static fn(): array => [
                ...(new Ziggy())->toArray(),
                'location' => $request->url(),
            ],
            'locale' => app()->getLocale(),
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
            ],
            'auth' => [
                'user' => static fn(): ?array => self::userPayload($request),
            ],
            'panel' => [
                'navigation' => self::navigationItems(),
            ],
        ];
    }

    /**
     * @param Request $request
     *
     * @return array{id: mixed, name: mixed, email: mixed}|null
     */
    private static function userPayload(Request $request): ?array
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
        ];
    }

    /**
     * @return array<int, array{label: string, route: string, icon: mixed}>
     */
    private static function navigationItems(): array
    {
        return collect(config('panel.navigation', []))
            ->map(static fn(array $item): array => [
                'label' => __(Arr::get($item, 'label', '')),
                'route' => Arr::get($item, 'route', ''),
                'icon' => Arr::get($item, 'icon'),
            ])
            ->values()
            ->all();
    }
}
