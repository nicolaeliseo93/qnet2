<?php

namespace App\DataObjects\Products;

/**
 * Validated payload for creating a product (POST /api/products, spec 0017).
 * Declared DTO (no "magic flying array") so the StoreProductRequest →
 * ProductService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects.
 */
final readonly class CreateProductData
{
    /**
     * @param  array<int, array{attribute_id: int, value: mixed}>|null  $attributes
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?float $cost,
        public ?float $price,
        public int $categoryId,
        public ?array $attributes = null,
    ) {}

    /**
     * Build from the validated StoreProductRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            cost: array_key_exists('cost', $data) && $data['cost'] !== null ? (float) $data['cost'] : null,
            price: array_key_exists('price', $data) && $data['price'] !== null ? (float) $data['price'] : null,
            categoryId: (int) $data['category_id'],
            attributes: array_key_exists('attributes', $data) ? (array) $data['attributes'] : null,
        );
    }
}
