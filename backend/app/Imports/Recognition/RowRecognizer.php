<?php

namespace App\Imports\Recognition;

use App\Imports\ImportRowContext;

/**
 * A pluggable staging-time rule that derives extra resolved values from a
 * row's already-mapped values — beyond a direct column-to-field mapping —
 * e.g. splitting a full name into first/last, or resolving a geo name to an
 * id (spec 0033). Registered per-definition via
 * `ImportDefinition::recognizers()` and run, in order, by StageImportJob:
 * each RecognitionResult's `resolved` values are merged into the staged
 * row's `resolved` column, and a `needsReview` result downgrades the row's
 * status to warning with its `messages` appended.
 *
 * Stateless and side-effect free: a recognizer only reads `$mapped` and
 * returns a result — it never touches the database or the row itself.
 */
interface RowRecognizer
{
    /**
     * @param  array<string, mixed>  $mapped  the row's values already keyed by field id (the mapping step's column->field assignment)
     */
    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult;
}
