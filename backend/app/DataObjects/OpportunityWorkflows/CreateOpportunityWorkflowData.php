<?php

namespace App\DataObjects\OpportunityWorkflows;

use Illuminate\Support\Collection;

/**
 * Validated payload for creating an opportunity workflow (POST
 * /api/opportunity-workflows, spec 0047 Lane A). Declared DTO (no "magic
 * flying array") so the StoreOpportunityWorkflowRequest ->
 * OpportunityWorkflowService contract is explicit.
 *
 * `criteria` is REQUIRED, min:1 (AC-008): a list of {field, value_id} pairs,
 * one per allow-listed field (App\Support\OpportunityWorkflows\
 * CriterionFieldRegistry). `statuses` is OPTIONAL — ONLY the intermediate
 * CUSTOM rows; the 2 system rows (open/closed, AC-004) are created
 * automatically by the Service via WorkflowStatusWriter, never accepted from
 * the client.
 */
final readonly class CreateOpportunityWorkflowData
{
    /**
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     * @param  array<int, array{name: string, color: ?string, group: string}>  $statuses
     */
    public function __construct(
        public string $name,
        public bool $isActive,
        public array $criteria,
        public array $statuses,
    ) {}

    /**
     * Build from the validated StoreOpportunityWorkflowRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            criteria: self::normalizeCriteria($data['criteria']),
            statuses: self::normalizeStatuses($data['statuses'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * @param  array<int, array{field: mixed, value_id: mixed}>  $criteria
     * @return array<int, array{field: string, value_id: int}>
     */
    public static function normalizeCriteria(array $criteria): array
    {
        return array_map(
            static fn (array $criterion): array => [
                'field' => (string) $criterion['field'],
                'value_id' => (int) $criterion['value_id'],
            ],
            $criteria,
        );
    }

    /**
     * @param  array<int, array{name: mixed, color?: mixed, group: mixed}>  $statuses
     * @return array<int, array{name: string, color: ?string, group: string}>
     */
    public static function normalizeStatuses(array $statuses): array
    {
        return array_map(
            static fn (array $status): array => [
                'name' => (string) $status['name'],
                'color' => array_key_exists('color', $status) ? $status['color'] : null,
                'group' => (string) $status['group'],
            ],
            $statuses,
        );
    }

    /**
     * The deterministic "field:value_id|..." string enforcing criteria-
     * combination uniqueness (AC-009): criteria sorted by `field` (each field
     * appears at most once per workflow, so this is equivalent to sorting the
     * full pair) before joining — the payload's submission ORDER never
     * affects the resulting signature.
     *
     * @param  array<int, array{field: mixed, value_id: mixed}>  $criteria
     */
    public static function computeSignature(array $criteria): string
    {
        return Collection::make($criteria)
            ->map(static fn (array $criterion): array => [
                'field' => (string) $criterion['field'],
                'value_id' => (int) $criterion['value_id'],
            ])
            ->sortBy('field')
            ->values()
            ->map(static fn (array $pair): string => "{$pair['field']}:{$pair['value_id']}")
            ->implode('|');
    }
}
