<?php

namespace App\DataObjects\Products;

use App\Enums\ProductType;

/**
 * Validated payload for creating a product (POST /api/products, spec 0017).
 * Declared DTO (no "magic flying array") so the StoreProductRequest →
 * ProductService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects. `cost`/`price`/`productType` are all required by the
 * FormRequest, so they cross as non-null values.
 */
final readonly class CreateProductData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public float $cost,
        public float $price,
        public int $categoryId,
        public ProductType $productType,
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
            cost: (float) $data['cost'],
            price: (float) $data['price'],
            categoryId: (int) $data['category_id'],
            productType: ProductType::from((string) $data['product_type']),
        );
    }
}
