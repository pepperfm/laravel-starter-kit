<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('renders json exceptions without optional api responder package', function () {
    Route::get('/_test-json-exception', static function (): void {
        throw new RuntimeException('Unexpected failure');
    });

    $this->getJson('/_test-json-exception')
        ->assertInternalServerError()
        ->assertJson([
            'data' => [],
            'message' => 'Unexpected failure',
        ]);
});

it('does not expose unexpected exception details when debug mode is disabled', function (): void {
    config()->set('app.debug', false);

    Route::get('/api/_test-hidden-error', static function (): void {
        throw new RuntimeException('SQL password or internal path');
    });

    $this->getJson('/api/_test-hidden-error')
        ->assertInternalServerError()
        ->assertJsonPath('message', 'Внутренняя ошибка сервера')
        ->assertDontSee('SQL password or internal path');
});

it('uses 403 for authorization failures', function (): void {
    Route::get('/api/_test-forbidden', static function (): void {
        throw new \Illuminate\Auth\Access\AuthorizationException();
    });

    $this->getJson('/api/_test-forbidden')
        ->assertForbidden()
        ->assertJsonPath('message', 'Доступ запрещён');
});

it('keeps Laravel field errors in validation responses', function (): void {
    Route::get('/api/_test-invalid', static function (): void {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => ['The email field is invalid.'],
        ]);
    });

    $this->getJson('/api/_test-invalid')
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The email field is invalid.');
});
