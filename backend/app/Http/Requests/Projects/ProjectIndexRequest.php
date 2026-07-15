<?php

namespace App\Http\Requests\Projects;

use App\DataObjects\Projects\ProjectIndexQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/projects (spec 0026, card-grid index,
 * D-3). Mirrors ProjectForSelectRequest, capped at the spec's own limit (60,
 * NOT BaseApiController::MAX_LIMIT — the card grid is deliberately smaller
 * than the generic table/for-select ceiling).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('viewAny', Project::class)).
 */
class ProjectIndexRequest extends FormRequest
{
    private const int MAX_LIMIT = 60;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProjectPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'pipeline_status_id' => ['sometimes', 'integer', Rule::exists('pipeline_statuses', 'id')],
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): ProjectIndexQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProjectIndexQuery::fromValidated($validated);
    }
}
