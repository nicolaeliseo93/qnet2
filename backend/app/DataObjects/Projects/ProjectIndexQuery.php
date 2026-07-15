<?php

declare(strict_types=1);

namespace App\DataObjects\Projects;

/**
 * Validated query for GET /api/projects (spec 0026, D-3: the card-grid
 * index, distinct from the table framework). Declared DTO (no "magic flying
 * array") so the ProjectIndexRequest -> ProjectService contract is explicit,
 * mirroring ForSelectQuery.
 *
 * - `search`: matches project `code` OR `name` (null/empty = no filter).
 * - `offset` / `limit`: pagination (limit capped by the FormRequest at 60).
 * - `pipelineStatusId`: exact status filter, null = no filter.
 * - `advancedFilters`: second-level backend-driven panel (spec 0032, AC-018),
 *   whitelist-validated by the FormRequest against ProjectsTableDefinition's
 *   own catalogue — same shape/keys as the AG Grid `POST /rows` payload.
 */
final readonly class ProjectIndexQuery
{
    /**
     * @param  array<string, mixed>  $advancedFilters
     */
    public function __construct(
        public ?string $search,
        public int $offset,
        public int $limit,
        public ?int $pipelineStatusId,
        public array $advancedFilters = [],
    ) {}

    /**
     * Build from the validated ProjectIndexRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $search = array_key_exists('search', $data) ? trim((string) $data['search']) : null;
        $advancedFilters = $data['advancedFilters'] ?? [];

        return new self(
            search: ($search === null || $search === '') ? null : $search,
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 12),
            pipelineStatusId: isset($data['pipeline_status_id']) ? (int) $data['pipeline_status_id'] : null,
            advancedFilters: is_array($advancedFilters) ? $advancedFilters : [],
        );
    }

    public function hasSearch(): bool
    {
        return $this->search !== null;
    }
}
