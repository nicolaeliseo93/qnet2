<?php

namespace App\Migrations;

/**
 * Validated pagination request for a source's read-only preview (phase 1,
 * spec 0013): `page` (1-based) and `per_page`, already bounded by
 * App\Http\Requests\Migration\MigrationPreviewRequest.
 */
final readonly class MigrationQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
