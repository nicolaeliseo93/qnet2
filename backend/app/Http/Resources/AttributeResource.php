<?php

namespace App\Http\Resources;

use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attribute
 */
class AttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'data_type' => $this->data_type,
            'options' => $this->options->map(fn (AttributeOption $option): array => [
                'id' => $option->id,
                'value' => $option->value,
                'label' => $option->label,
                'sort_order' => $option->sort_order,
            ])->all(),
            'created_at' => $this->created_at,
        ];
    }
}
