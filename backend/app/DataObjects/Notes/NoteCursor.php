<?php

namespace App\DataObjects\Notes;

use InvalidArgumentException;

/**
 * Opaque keyset-pagination cursor for GET /api/notes (spec 0052, D-13):
 * base64("{created_at}|{id}"), matching the `created_at desc, id desc` order
 * root notes are paginated by. Mirrors App\DataObjects\ActivityLog\ActivityLogCursor
 * (same precedent, different feed).
 */
final readonly class NoteCursor
{
    public function __construct(
        public string $createdAt,
        public int $id,
    ) {}

    /**
     * @throws InvalidArgumentException when the cursor is not well-formed
     */
    public static function decode(string $encoded): self
    {
        $decoded = base64_decode($encoded, strict: true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Malformed note cursor.');
        }

        $parts = explode('|', $decoded, 2);

        if (count($parts) !== 2 || $parts[0] === '' || ! ctype_digit($parts[1])) {
            throw new InvalidArgumentException('Malformed note cursor.');
        }

        return new self($parts[0], (int) $parts[1]);
    }

    public function encode(): string
    {
        return base64_encode("{$this->createdAt}|{$this->id}");
    }
}
