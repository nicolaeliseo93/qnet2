<?php

namespace App\Http\Requests\Projects;

use App\DataObjects\Projects\ProjectIndexQuery;
use App\Services\Table\AdvancedFilterApplier;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the query for GET /api/projects (spec 0026, card-grid index,
 * D-3). Mirrors ProjectForSelectRequest, capped at the spec's own limit (60,
 * NOT BaseApiController::MAX_LIMIT — the card grid is deliberately smaller
 * than the generic table/for-select ceiling).
 *
 * `advancedFilters` (spec 0032, AC-018) is whitelist-validated the SAME way
 * as TableRowsRequest: every key must be within ProjectsTableDefinition's own
 * advancedFilters() catalogue and its value structurally valid for the
 * descriptor's type (delegated to AdvancedFilterApplier::validate(), no
 * duplicated per-type logic) — a key outside the catalogue or a malformed
 * value 422s, never silently reaching the query.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('viewAny', Project::class)).
 */
class ProjectIndexRequest extends FormRequest
{
    private const int MAX_LIMIT = 60;

    private ?TableDefinition $resolvedDefinition = null;

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

            // Second-level, backend-driven advanced filters (spec 0032,
            // AC-018). Keys and value shapes are whitelisted against the
            // ProjectsTableDefinition's advancedFilters() catalogue in
            // withValidator() below — identical allow-list as POST /rows.
            'advancedFilters' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Every `advancedFilters` key must be a declared filter on the `projects`
     * domain (allow-list), its value structurally valid for the descriptor's
     * type.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $advancedFilters = $this->input('advancedFilters');

            if (! is_array($advancedFilters)) {
                return;
            }

            $catalog = array_column($this->definition()->advancedFilters(), null, 'name');
            $errors = app(AdvancedFilterApplier::class)->validate($catalog, $advancedFilters);

            foreach ($errors as $name => $message) {
                $validator->errors()->add("advancedFilters.{$name}", $message);
            }
        });
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

    /**
     * The `projects` domain's TableDefinition, resolved once per request —
     * same registry TableController uses, so the allow-list is the single
     * source of truth shared by both the card-grid and the AG Grid table.
     */
    private function definition(): TableDefinition
    {
        if ($this->resolvedDefinition === null) {
            $this->resolvedDefinition = app(TableRegistry::class)->resolve('projects');
        }

        return $this->resolvedDefinition;
    }
}
