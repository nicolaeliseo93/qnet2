<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * One bar of a DistributionWidget. `label` is a DOMAIN value read from the
 * database (a source name, a status label) — NOT an i18n key: the frontend
 * renders it verbatim (spec 0026).
 */
final readonly class DistributionItem
{
    public function __construct(
        public string $key,
        public string $label,
        public int $value,
        public ?string $color = null,
    ) {}

    /**
     * @return array{key: string, label: string, value: int, color: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'color' => $this->color,
        ];
    }
}
