<?php

namespace App\Http\Controllers\Abstract;

use App\Enums\HttpStatusEnum;
use App\Services\TeamsWebhookService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class BaseApiController
{
    const int MAX_LIMIT = 100;

    /** HELPERS */
    public function paginatedResponse(
        mixed $items,
        int $total,
        int $offset = 1,
        int $limit = self::MAX_LIMIT,
        ?string $exportLink = null,
        ?array $meta = null
    ): JsonResponse {
        $payload = [
            'items' => $items,
            'export_link' => $exportLink,
            'pagination' => [
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];

        if (! is_null($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload);
    }

    public function response(mixed $data): JsonResponse
    {
        return response()->json($data);
    }

    public function validateRequest(
        Request $request
    ): void {
        $maxLimit = self::MAX_LIMIT;
        $request->validate([
            'offset' => 'sometimes|integer|min:0',
            'limit' => "sometimes|integer|min:1|max:$maxLimit",
        ]);
    }

    public function limit(mixed $page = 1): int
    {
        return (int) ($page ?? 1);
    }

    public function offset(mixed $perPage = 15): int
    {
        return (int) ($perPage ?? 15);
    }

    protected function ok(mixed $data = null, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status->value);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->ok($data, $message, HttpStatusEnum::CREATED);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, HttpStatusEnum::NO_CONTENT->value);
    }

    protected function fail(string $message, int $status, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Convert unexpected controller exceptions into a consistent API response.
     */
    protected function handleControllerException(Throwable $exception, string $method, array $parameters = []): JsonResponse
    {
        $status = $this->resolveExceptionStatus($exception);
        $message = $this->resolveExceptionMessage($exception, $status);
        $backendTimestamp = now()->toIso8601String();

        Log::error('[BACKEND] API internal error', [
            'log_origin' => 'backend',
            'backend_timestamp' => $backendTimestamp,
            'controller' => static::class,
            'action' => $method,
            'status' => $status,
            'message' => $exception->getMessage(),
            'exception' => get_class($exception),
            'parameters' => $parameters,
            'url' => request()->fullUrl(),
            'method_http' => request()->method(),
            'user_id' => auth()->id(),

            'error code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack trace' => $exception->getTraceAsString(),
        ]);

        if (config('services.teams.enabled')) {
            app(TeamsWebhookService::class)->sendError(
                title: '[BACKEND] API internal error',
                message: $exception->getMessage(),
                facts: [
                    'Log Origin' => 'backend',
                    'Backend Timestamp' => $backendTimestamp,
                    'Controller' => static::class,
                    'Action' => $method,
                    'Status' => $status,
                    'Exception' => get_class($exception),
                    'URL' => request()->fullUrl(),
                    'HTTP Method' => request()->method(),
                    'User ID' => auth()->id() ?? 'guest',

                    'error code' => $exception->getCode(),
                    'get message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'stack trace' => $exception->getTraceAsString(),
                ]
            );
        }

        return $this->fail($message, $status);
    }

    /**
     * Resolve the HTTP status associated with the thrown exception.
     */
    protected function resolveExceptionStatus(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof AuthorizationException => HttpStatusEnum::FORBIDDEN->value,
            $exception instanceof ModelNotFoundException => HttpStatusEnum::NOT_FOUND->value,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => HttpStatusEnum::INTERNAL_SERVER_ERROR->value,
        };
    }

    /**
     * Return a safe error message for the API response.
     */
    protected function resolveExceptionMessage(Throwable $exception, int $status): string
    {
        if ($status >= HttpStatusEnum::INTERNAL_SERVER_ERROR->value && ! app()->hasDebugModeEnabled()) {
            return 'An unexpected error occurred.';
        }

        return $exception->getMessage() ?: 'An unexpected error occurred.';
    }
}
