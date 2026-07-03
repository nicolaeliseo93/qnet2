<?php

use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fail-closed hard gate for the "Migrazioni" section (spec 0013).
        $middleware->alias([
            'super-admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Uniform 404 envelope for unknown {domain} on api/* routes. A
        // ModelNotFoundException can be thrown from a FormRequest (e.g.
        // TableRowsRequest resolving an unregistered domain) BEFORE the
        // controller's try/catch runs, which would otherwise yield Laravel's
        // default 404 body instead of the contract's fail() shape
        // ({success:false, message}). This keeps that response on-contract
        // (status and pre-validation behaviour are unchanged).
        //
        // Note: Handler::prepareException() converts ModelNotFoundException to a
        // NotFoundHttpException (keeping the original as its previous) BEFORE
        // render callbacks run, so we match on NotFoundHttpException and only
        // handle the model-not-found case — route-not-found 404s are untouched.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*') || ! ($exception->getPrevious() instanceof ModelNotFoundException)) {
                return null;
            }

            // Generic message: never leak the internal model/definition class.
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], Response::HTTP_NOT_FOUND);
        });
    })->create();
