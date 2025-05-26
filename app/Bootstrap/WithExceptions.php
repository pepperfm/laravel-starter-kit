<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pepperfm\ApiBaseResponder\Contracts\ResponseContract;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WithExceptions
{
    public function __invoke(Exceptions $exceptions): void
    {
        $exceptions->render($this->render());
    }

    private function render(): \Closure
    {
        return static function (\Exception $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                $json = app(ResponseContract::class);

                logger()->error($e->getMessage(), [
                    'trace_as_string' => $e->getTraceAsString(),
                ]);

                if (
                    $e instanceof \Illuminate\Auth\AuthenticationException ||
                    $e instanceof \Illuminate\Auth\Access\AuthorizationException
                ) {
                    return $json->response(
                        data: [],
                        message: 'Ошибка авторизации',
                        httpStatusCode: JsonResponse::HTTP_UNAUTHORIZED
                    );
                }
                if ($e instanceof NotFoundHttpException) {
                    return $json->response(
                        data: [],
                        message: 'Запись не найдена',
                        httpStatusCode: JsonResponse::HTTP_NOT_FOUND
                    );
                }
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return $json->response(
                        data: [],
                        message: $e->getMessage(),
                        httpStatusCode: JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                return $json->response([], message: $e->getMessage(), httpStatusCode: $e->getStatusCode());
            }
        };
    }
}
