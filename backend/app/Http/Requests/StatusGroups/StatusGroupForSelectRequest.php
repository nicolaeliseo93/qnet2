<?php

namespace App\Http\Requests\StatusGroups;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/status-groups/for-select (ADR 0011),
 * mirroring LeadStatusForSelectRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', StatusGroup::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 */
class StatusGroupForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the StatusGroupPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}
