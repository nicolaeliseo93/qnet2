<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Services\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PUBLIC application bootstrap. GET /api/config exposes the non-sensitive
 * presentation metadata (domain enum options for selects/badges) the frontend
 * needs before authentication. Thin controller: it forwards the request locale
 * hint to the service and returns the assembled payload.
 *
 * SECURITY: the endpoint is unauthenticated (outside auth:sanctum) and
 * rate-limited (throttle:30,1). The exposed surface is a fixed server-side
 * allowlist (config/config.php) — never request input — so no arbitrary class
 * can be reflected. See ADR 0008.
 */
class ConfigController extends BaseApiController
{
    public function __construct(private readonly ConfigService $config) {}

    /**
     * GET /api/config — public bootstrap payload (data.enums, extensible).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->config->bootstrap($request->header('Accept-Language')));
    }
}
