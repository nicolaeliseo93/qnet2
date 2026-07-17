<?php

namespace App\DataObjects\Shared;

/**
 * Validated query for a for-select endpoint (GET /api/{resource}/for-select).
 *
 * Declared DTO (no "magic flying array") carrying the search/pagination/hydration
 * inputs from the FormRequest into the Service — see standards/architecture.md →
 * Data Transfer Objects and ADR 0011.
 *
 * - `search`: case-insensitive substring match (null/empty = no filter).
 * - `offset` / `limit`: pagination (limit capped by the FormRequest at MAX_LIMIT).
 * - `ids`: edit-mode hydration — these ids are appended deduplicated, bypass the
 *   search filter and do NOT inflate the total.
 * - `businessFunctionId` (spec 0040 amendment rev.3): ADDITIVE, consumed ONLY
 *   by ProductCategoryService::forSelect — every other for-select consumer
 *   defaults it to null (retrocompatible, no behaviour change).
 */
final readonly class ForSelectQuery
{
    /**
     * @param  array<int, int>  $ids
     */
    public function __construct(
        public ?string $search,
        public int $offset,
        public int $limit,
        public array $ids,
        public ?int $businessFunctionId = null,
    ) {}

    /**
     * Build from a validated for-select FormRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $search = array_key_exists('search', $data) ? trim((string) $data['search']) : null;

        /** @var array<int, int> $ids */
        $ids = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            (array) ($data['ids'] ?? []),
        )));

        return new self(
            search: ($search === null || $search === '') ? null : $search,
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 25),
            ids: $ids,
            businessFunctionId: isset($data['business_function_id']) ? (int) $data['business_function_id'] : null,
        );
    }

    public function hasSearch(): bool
    {
        return $this->search !== null;
    }

    public function hasIds(): bool
    {
        return $this->ids !== [];
    }
}
