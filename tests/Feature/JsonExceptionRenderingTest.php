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
