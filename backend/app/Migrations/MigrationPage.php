<?php

namespace App\Migrations;

/**
 * One normalized page of a source's read-only preview (spec 0013): the
 * external records already mapped to rows keyed by column id, plus the
 * pagination the frontend needs to page prev/next. `total` is null when the
 * external system's response carried no total (AC-007).
 */
final readonly class MigrationPage
{
    /**
     * @param  array<int, array<string, string|int|bool|null>>  $rows
     */
    public function __construct(
        public array $rows,
        public int $page,
        public int $perPage,
        public ?int $total,
        public bool $hasMore,
    ) {}
}
