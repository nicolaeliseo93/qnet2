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
 * - `projectStatusId`: exact status filter, null = no filter.
 */
final readonly class ProjectIndexQuery
{
    public function __construct(
        public ?string $search,
        public int $offset,
        public int $limit,
        public ?int $projectStatusId,
    ) {}

    /**
     * Build from the validated ProjectIndexRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $search = array_key_exists('search', $data) ? trim((string) $data['search']) : null;

        return new self(
            search: ($search === null || $search === '') ? null : $search,
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 12),
            projectStatusId: isset($data['project_status_id']) ? (int) $data['project_status_id'] : null,
        );
    }

    public function hasSearch(): bool
    {
        return $this->search !== null;
    }
}
