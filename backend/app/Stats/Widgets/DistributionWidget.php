<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * A breakdown of the domain by one dimension (spec 0026). `total` is the
 * denominator of the percentages the frontend draws — the FULL population,
 * which may exceed the sum of `items` when the list is capped (top N) or when
 * rows carry no value for the dimension. 0 is admitted (frontend shows 0%).
 */
final readonly class DistributionWidget implements Widget
{
    /**
     * @param  array<int, DistributionItem>  $items
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $items,
        public int $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'distribution',
            'key' => $this->key,
            'label' => $this->label,
            'items' => array_map(
                static fn (DistributionItem $item): array => $item->toArray(),
                $this->items,
            ),
            'total' => $this->total,
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
