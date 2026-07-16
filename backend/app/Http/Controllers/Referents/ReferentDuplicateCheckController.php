<?php

namespace App\Http\Controllers\Referents;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Referents\CheckReferentDuplicatesRequest;
use App\Http\Resources\ReferentDuplicateMatchResource;
use App\Models\Referent;
use App\Services\ReferentDuplicateFinder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/referents/duplicate-check — live, non-blocking duplicate check
 * for the referent create form (spec 0037): given a tax_code and/or
 * email/phone/mobile contacts, returns the (max 5) EXISTING referents that
 * collide, with the matched channel(s).
 *
 * Thin invokable controller: validation (CheckReferentDuplicatesRequest),
 * server-side authorization (referents.create — the same gate the actual
 * creation checks, since this feeds it), Service call, Resource response.
 *
 * @see ReferentDuplicateFinder::find
 */
class ReferentDuplicateCheckController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ReferentDuplicateFinder $finder) {}

    public function __invoke(CheckReferentDuplicatesRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Referent::class);

            $matches = $this->finder->find($request->toCriteria());

            return $this->ok(['matches' => ReferentDuplicateMatchResource::collection($matches)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
