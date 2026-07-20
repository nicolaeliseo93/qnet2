<?php

namespace App\Http\Requests\Statuses;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/{pipeline-statuses|opportunity-statuses}/reorder
 * (spec 0039, D-5). Shared by both status configurators — the shape is
 * identical, only the target resource/model differs (resolved by the
 * concrete controller).
 *
 * `distinct` is the shape-level guard (no duplicate id submitted twice); the
 * SEMANTIC guard — the set must be exactly every custom status id, no system
 * row, none missing — is a full-table-state check StatusOrderManager::
 * reorder() owns instead (a single FormRequest rule cannot express a "set
 * equality against the DB" constraint).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, gated on `{resource}.update` — no single Model instance
 * exists for a bulk reorder, so there is no Policy `update($user, $model)`
 * to delegate to).
 */
class ReorderStatusesRequest extends FormRequest
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
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function orderedIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('ordered_ids');

        return array_map(intval(...), $ids);
    }
}
