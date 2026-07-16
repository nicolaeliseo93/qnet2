<?php

namespace App\DataObjects\ActivityLog;

use InvalidArgumentException;

/**
 * Opaque keyset-pagination cursor for the aggregated activity log (spec 0034):
 * base64("{created_at}|{id}"), matching the `created_at desc, id desc` order
 * AggregatedActivityService paginates by. `created_at` is carried as the raw
 * DB string (no date parsing) since the cursor is only ever compared back
 * against that same column, never displayed.
 */
final readonly class ActivityLogCursor
{
    public function __construct(
        public string $createdAt,
        public int $id,
    ) {}

    /**
     * @throws InvalidArgumentException when the cursor is not well-formed.
     */
    public static function decode(string $encoded): self
    {
        $decoded = base64_decode($encoded, strict: true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Malformed activity log cursor.');
        }

        $parts = explode('|', $decoded, 2);

        if (count($parts) !== 2 || $parts[0] === '' || ! ctype_digit($parts[1])) {
            throw new InvalidArgumentException('Malformed activity log cursor.');
        }

        return new self($parts[0], (int) $parts[1]);
    }

    public function encode(): string
    {
        return base64_encode("{$this->createdAt}|{$this->id}");
    }
}
