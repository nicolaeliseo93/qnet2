<?php

namespace App\Imports\Recognition;

/**
 * Outcome of a single RowRecognizer::recognize() call: the field values it
 * resolved, whether the row should be flagged for manual review despite
 * being resolved (a low-confidence best-effort split, an ambiguous geo
 * match, ...), and the motivated messages driving that flag.
 */
final readonly class RecognitionResult
{
    private function __construct(
        public array $resolved,
        public bool $needsReview,
        public array $messages,
    ) {}

    /**
     * @param  array<string, mixed>  $resolved
     * @param  array<int, string>  $messages
     */
    public static function resolved(array $resolved, bool $needsReview = false, array $messages = []): self
    {
        return new self($resolved, $needsReview, $messages);
    }

    /**
     * Nothing to resolve — the recognizer's input field(s) were absent or
     * already resolved by a direct mapping. A true no-op: never flags the
     * row and merges nothing.
     */
    public static function none(): self
    {
        return new self([], false, []);
    }
}
