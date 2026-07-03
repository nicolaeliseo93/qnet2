<?php

namespace App\Migrations;

use App\Models\MigrationRun;
use App\Models\User;

/**
 * Everything a MigrationSource needs to run its import phase (spec 0013):
 * the run being processed (mutated in place with the row counters/report by
 * AbstractMigrationSource) and the acting user (super-admin, gated by
 * EnsureSuperAdmin) the domain Services create records on behalf of.
 */
final readonly class MigrationImportContext
{
    public function __construct(
        public MigrationRun $run,
        public User $actor,
    ) {}
}
