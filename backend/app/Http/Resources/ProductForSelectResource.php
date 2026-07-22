<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * For-select projection of a Product (GET /api/products/for-select).
 *
 * Minimal by design (ADR 0011): label = name, `subtitle` = the product's own
 * category (eager-loaded by ProductService::forSelect, never a query per
 * row) — the "prodotti di interesse" picker shows it so a cross-category
 * pick is recognizable BEFORE it adds a product line to the opportunity.
 *
 * @mixin Product
 */
class ProductForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->category?->name,
        ];
    }
}
