<?php

namespace App\Enums;

/**
 * Lifecycle of an external-data migration run (spec 0013): `pending` (queued,
 * not yet picked up) -> `processing` (RunMigrationJob is paging through the
 * external source) -> `completed`. `failed` is reachable from `pending` OR
 * `processing` on any unhandled job exception, so a run never stays stuck
 * mid-flight.
 */
enum MigrationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
