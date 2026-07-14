<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * The optional secondary line under a StatWidget value: an i18n KEY plus the
 * count it interpolates (e.g. "42 converted"). Never translated server-side
 * (spec 0026, D-4).
 */
final readonly class StatSubtitle
{
    public function __construct(
        public string $key,
        public int $count,
    ) {}

    /**
     * @return array{key: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'count' => $this->count,
        ];
    }
}
