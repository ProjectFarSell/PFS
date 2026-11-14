<?php

namespace App\Services\Mobile;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiErrors
{
    public static function applies(Request $request): bool
    {
        return $request->is('api/v1/*') && ! $request->is('api/v1/catalog', 'api/v1/health');
    }

    public static function render(Throwable $exception, Request $request)
    {
        if (! self::applies($request)) {
            return null;
        }
        $status = match (true) {
            $exception instanceof ApiFailure => $exception->status,
            $exception instanceof QueryException && (($exception->errorInfo[0] ?? '') === '40001' || in_array((int) ($exception->errorInfo[1] ?? 0), [5, 6, 1205, 1213], true)) => 409,
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
        $code = $exception instanceof ApiFailure ? $exception->errorCode : match ($status) {
            401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 409 => 'state_conflict', 422 => 'validation_failed', 429 => 'rate_limited', 503 => 'service_unavailable', default => $status >= 500 ? 'internal_error' : 'invalid_request'
        };
        $body = ['message' => $exception instanceof ApiFailure ? $exception->getMessage() : match ($status) {
            401 => 'Sign in to continue.', 403 => 'You cannot perform this action.', 404 => 'This record was not found.', 422 => 'Please check the supplied fields.', 429 => 'Too many requests. Please wait.', default => $status >= 500 ? 'The service is temporarily unavailable.' : 'The request could not be completed.'
        }, 'code' => $code];
        if ($exception instanceof ValidationException) {
            $body['errors'] = $exception->errors();
        }
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        if ($status === 429) {
            $headers['Retry-After'] ??= '60';
        }

        return response()->json($body, $status, [...$headers, 'Cache-Control' => 'private, no-store']);
    }

    public static function report(Throwable $exception): ?bool
    {
        if (! app()->bound('request') || ! self::applies(request())) {
            return null;
        }
        // Exceptions may embed SQL values or provider bodies. Never log them for API requests.
        if ($exception instanceof ApiFailure && $exception->status < 500) {
            return false;
        }
        $app = request()->user()?->role->value;
        Log::channel('operations')->error('mobile_api_error', ['exception' => $exception::class, 'route' => request()->route()?->getName(), 'api_version' => 'v1', 'app' => in_array($app, ['buyer', 'rider'], true) ? $app : 'unknown']);

        return false;
    }
}
