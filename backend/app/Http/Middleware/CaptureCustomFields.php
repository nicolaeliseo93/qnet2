<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\CustomFields\CustomFieldRequestBag;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures the request-wide `custom_fields` payload (spec 0021 — INNESTO
 * WRITE) into the request-scoped CustomFieldRequestBag, so
 * App\Models\Concerns\HasCustomFields' `saving`/`saved` observers can
 * validate/persist it without coupling the model layer to HTTP.
 *
 * Pure capture, nothing else: no authentication/authorization here — the
 * resource's own Policy (unchanged) still gates the base create/update
 * ability, and CustomFieldValidator (run from the model's `saving` event)
 * is the write pipeline's own authorization/validation gate.
 */
class CaptureCustomFields
{
    public function handle(Request $request, Closure $next): Response
    {
        $customFields = $request->input('custom_fields');

        if (is_array($customFields)) {
            app(CustomFieldRequestBag::class)->set($customFields);
        }

        return $next($request);
    }
}
