<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe sequential code generator (spec 0023, BR-1):
 * "{prefix}-{seq:4}", zero-padded to 4 digits, no reuse of holes.
 *
 * MUST be called from inside an already-open DB::transaction (the caller's
 * create()): the pessimistic lock taken here (`FOR UPDATE`) is released only
 * when that transaction commits/rolls back, which is what makes two
 * concurrent creates serialize on the same next code instead of racing.
 * Generic on $table/$column/$prefix so both ProjectService (PRJ-) and
 * CampaignService (CMP-) share one implementation.
 */
trait GeneratesSequentialCode
{
    private const int CODE_PAD_LENGTH = 4;

    /**
     * The next "{prefix}-0001" code for $table.$column.
     */
    protected function nextSequentialCode(string $table, string $column, string $prefix): string
    {
        // Locks every row matching this prefix (defence in depth against a
        // concurrent create racing on the same next sequence); a genuinely
        // empty prefix locks nothing and relies on the column's unique
        // constraint to reject a rare double-first-insert race.
        $lastCode = DB::table($table)
            ->where($column, 'like', $prefix.'-%')
            ->lockForUpdate()
            ->max($column);

        $nextSequence = $lastCode === null
            ? 1
            : ((int) substr((string) $lastCode, strlen($prefix) + 1)) + 1;

        return sprintf('%s-%0'.self::CODE_PAD_LENGTH.'d', $prefix, $nextSequence);
    }
}
