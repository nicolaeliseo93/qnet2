<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * A time series over the last N months (spec 0026). Empty months are present
 * with value 0 — the series is always dense, so the frontend never has to
 * reconstruct the missing buckets.
 */
final readonly class TrendWidget implements Widget
{
    /**
     * @param  array<int, TrendPoint>  $points
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $points,
        public StatFormat $format = StatFormat::Number,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'trend',
            'key' => $this->key,
            'label' => $this->label,
            'points' => array_map(
                static fn (TrendPoint $point): array => $point->toArray(),
                $this->points,
            ),
            'format' => $this->format->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
