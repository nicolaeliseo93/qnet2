<?php

namespace App\Enums;

/**
 * Duplicate-handling strategy for a staged import row (`import_run_rows`,
 * spec 0033) whose natural key matches an existing database record:
 * `create_only` (legacy 5 domains, spec 0012 — a match is always rejected as
 * a duplicate, never updated), `create_new` (ignore the match, always insert
 * a new record), `update_existing` (update the matched record in place),
 * `ignore` (skip the row, no write) and `manual` (leave the row `duplicate`
 * in the review grid for the user to decide before confirm).
 */
enum ImportDedupMode: string
{
    case CreateOnly = 'create_only';
    case CreateNew = 'create_new';
    case UpdateExisting = 'update_existing';
    case Ignore = 'ignore';
    case Manual = 'manual';
}
