<?php

namespace App\Http\Controllers\CustomFields;

use App\CustomFields\CustomFieldEntityRegistry;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\CustomFieldDefinition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/custom-fields/entities — the custom-fieldable domains (spec 0021
 * — ADMIN CRUD DEFINIZIONI), feeding the admin form's entity_type picker.
 *
 * Thin invokable controller: server-side authorization
 * (custom-fields.viewAny via CustomFieldDefinitionPolicy), registry call,
 * response. Mirrors BusinessFunctionForSelectController's shape.
 */
class CustomFieldEntitiesController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CustomFieldEntityRegistry $registry) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', CustomFieldDefinition::class);

            return $this->ok($this->registry->entities());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
