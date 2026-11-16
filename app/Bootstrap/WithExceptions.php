<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WithExceptions
{
    public function __invoke(Exceptions $exceptions): void
    {
        $exceptions->render(static function (\Throwable $e, Request $request): ?JsonResponse {
            if (!$request->wantsJson() && !$request->is('api/*')) {
                return null;
            }

            // Laravel already reports exceptions: logging here would duplicate every error.
            if ($e instanceof ValidationException) {
                return self::jsonErrorResponse(
                    message: $e->getMessage(),
                    httpStatusCode: $e->status,
                    errors: $e->errors(),
                );
            }

            if ($e instanceof AuthenticationException) {
                return self::jsonErrorResponse('Требуется авторизация', JsonResponse::HTTP_UNAUTHORIZED);
            }

            if ($e instanceof AuthorizationException) {
                return self::jsonErrorResponse('Доступ запрещён', JsonResponse::HTTP_FORBIDDEN);
            }

            if ($e instanceof NotFoundHttpException) {
                return self::jsonErrorResponse('Запись не найдена', JsonResponse::HTTP_NOT_FOUND);
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : JsonResponse::HTTP_INTERNAL_SERVER_ERROR;

            $message = $status >= 500 && !config('app.debug')
                ? 'Внутренняя ошибка сервера'
                : ($e->getMessage() ?: (string) JsonResponse::$statusTexts[$status] ?? 'Ошибка запроса');

            return self::jsonErrorResponse($message, $status);
        });
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private static function jsonErrorResponse(
        string $message,
        int $httpStatusCode,
        array $errors = [],
    ): JsonResponse {
        // Validation errors must remain accessible to API clients as an "errors" object.
        if ($errors !== []) {
            return response()->json([
                'data' => [],
                'message' => $message,
                'errors' => $errors,
            ], $httpStatusCode);
        }

        if (interface_exists(\Pepperfm\ApiBaseResponder\Contracts\ResponseContract::class)) {
            return app(\Pepperfm\ApiBaseResponder\Contracts\ResponseContract::class)
                ->response(data: [], message: $message, httpStatusCode: $httpStatusCode);
        }

        return response()->json([
            'data' => [],
            'message' => $message,
        ], $httpStatusCode);
    }
}
