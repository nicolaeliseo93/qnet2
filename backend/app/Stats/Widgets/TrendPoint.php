<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * One point of a TrendWidget series. `label` is the ISO month bucket
 * (`YYYY-MM`); the frontend localizes it.
 */
final readonly class TrendPoint
{
    public function __construct(
        public string $label,
        public int|float $value,
    ) {}

    /**
     * @return array{label: string, value: int|float}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
