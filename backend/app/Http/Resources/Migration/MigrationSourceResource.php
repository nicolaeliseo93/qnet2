<?php

namespace App\Http\Resources\Migration;

use App\Migrations\MigrationSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MigrationSource
 *
 * The frozen `sources` item shape (spec 0013 data_contract): `{key, label}`.
 */
class MigrationSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MigrationSource $source */
        $source = $this->resource;

        return [
            'key' => $source->key(),
            'label' => $source->label(),
        ];
    }
}
