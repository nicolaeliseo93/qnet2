<?php

declare(strict_types=1);

namespace App\Http\Requests\OpportunityWorkflows;

use App\Enums\StatusGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT /api/opportunity-workflows/default-statuses
 * (spec 0047 Lane A): the GLOBAL default status set's custom rows (the same
 * shape as a workflow's own `statuses`, minus the workflow-scoping — this
 * endpoint always targets `opportunity_workflow_id` null). `statuses.*.id`
 * optional: present = update an existing row (custom OR system — a system
 * row only accepts name/color, enforced at the WorkflowStatusWriter layer),
 * absent = a new custom row.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('opportunity-workflows.update')).
 */
class UpdateDefaultStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array'],
            'statuses.*.id' => ['sometimes', 'integer'],
            'statuses.*.name' => ['required', 'string', 'max:191'],
            'statuses.*.color' => ['nullable', 'string', 'max:32'],
            'statuses.*.group' => ['required', Rule::enum(StatusGroup::class)],
        ];
    }

    /**
     * @return array<int, array{id: ?int, name: string, color: ?string, group: string}>
     */
    public function statuses(): array
    {
        /** @var array<int, array<string, mixed>> $statuses */
        $statuses = $this->validated('statuses');

        return array_map(
            static fn (array $status): array => [
                'id' => isset($status['id']) ? (int) $status['id'] : null,
                'name' => (string) $status['name'],
                'color' => array_key_exists('color', $status) ? $status['color'] : null,
                'group' => (string) $status['group'],
            ],
            $statuses,
        );
    }
}
