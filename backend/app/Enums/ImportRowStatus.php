<?php

namespace App\Enums;

/**
 * Outcome of a single staged row (`import_run_rows`, spec 0033) after
 * mapping/recognizers/dedup have run: `valid` (ready to persist), `warning`
 * (persistable but flagged for review, e.g. low-confidence name split or
 * ambiguous geo match), `error` (blocking, excluded from ProcessImportJob),
 * `duplicate` (matched an existing record, outcome depends on dedup
 * strategy) and `skipped` (excluded on purpose, e.g. `ignore` strategy).
 */
enum ImportRowStatus: string
{
    case Valid = 'valid';
    case Warning = 'warning';
    case Error = 'error';
    case Duplicate = 'duplicate';
    case Skipped = 'skipped';
}
