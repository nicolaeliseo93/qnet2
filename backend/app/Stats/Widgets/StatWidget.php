<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * A single scalar KPI (spec 0026): `value` is null when the datum does not
 * exist (e.g. a percent whose denominator is 0 — never "0%", see
 * App\Support\ConversionRate).
 */
final readonly class StatWidget implements Widget
{
    public function __construct(
        public string $key,
        public string $label,
        public int|float|null $value,
        public StatFormat $format = StatFormat::Number,
        public ?StatSubtitle $subtitle = null,
        public ?string $icon = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'stat',
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'format' => $this->format->value,
            'subtitle' => $this->subtitle?->toArray(),
            'icon' => $this->icon,
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
