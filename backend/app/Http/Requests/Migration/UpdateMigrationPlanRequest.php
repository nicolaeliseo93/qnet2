<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/migrations/plan (spec 0046): the ordered mass-import plan.
 * `sources` is a non-empty list of {source, enabled}; every `source` must be a
 * registered migration source (config/migrations.php) and distinct.
 *
 * Authorization is NOT handled here: the route group's `super-admin` middleware
 * alias (EnsureSuperAdmin) gates every migrations endpoint before this request
 * runs.
 */
class UpdateMigrationPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $registered */
        $registered = array_keys((array) config('migrations.definitions', []));

        return [
            'sources' => ['required', 'array', 'min:1'],
            'sources.*.source' => ['required', 'string', Rule::in($registered), 'distinct'],
            'sources.*.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * The validated ordered plan.
     *
     * @return list<array{source: string, enabled: bool}>
     */
    public function plan(): array
    {
        /** @var array<int, array{source: string, enabled: mixed}> $sources */
        $sources = $this->validated('sources');

        return array_map(
            static fn (array $item): array => [
                'source' => $item['source'],
                'enabled' => (bool) $item['enabled'],
            ],
            array_values($sources),
        );
    }
}
