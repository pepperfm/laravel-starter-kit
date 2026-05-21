<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WithExceptions
{
    public function __invoke(Exceptions $exceptions): void
    {
        $exceptions->render($this->render());
    }

    private function render(): \Closure
    {
        return static function (\Throwable $e, Request $request) {
            if ($request->wantsJson() || $request->is('api/*')) {
                logger()->error($e->getMessage(), [
                    'trace_as_string' => $e->getTraceAsString(),
                ]);

                if (
                    $e instanceof \Illuminate\Auth\AuthenticationException ||
                    $e instanceof \Illuminate\Auth\Access\AuthorizationException
                ) {
                    return self::jsonErrorResponse(
                        data: [],
                        message: 'Ошибка авторизации',
                        httpStatusCode: JsonResponse::HTTP_UNAUTHORIZED
                    );
                }
                if ($e instanceof NotFoundHttpException) {
                    return self::jsonErrorResponse(
                        data: [],
                        message: 'Запись не найдена',
                        httpStatusCode: JsonResponse::HTTP_NOT_FOUND
                    );
                }
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return self::jsonErrorResponse(
                        data: [],
                        message: $e->getMessage(),
                        httpStatusCode: JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $e->getStatusCode()
                    : JsonResponse::HTTP_INTERNAL_SERVER_ERROR;

                return self::jsonErrorResponse([], message: $e->getMessage(), httpStatusCode: $statusCode);
            }
        };
    }

    /**
     * @param array<mixed> $data
     * @param string $message
     * @param int $httpStatusCode
     */
    private static function jsonErrorResponse(
        array $data,
        string $message,
        int $httpStatusCode,
    ): JsonResponse {
        if (interface_exists(\Pepperfm\ApiBaseResponder\Contracts\ResponseContract::class)) {
            return app(\Pepperfm\ApiBaseResponder\Contracts\ResponseContract::class)
                ->response(data: $data, message: $message, httpStatusCode: $httpStatusCode);
        }

        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $httpStatusCode);
    }
}
