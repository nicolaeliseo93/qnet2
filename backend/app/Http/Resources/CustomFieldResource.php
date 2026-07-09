<?php

namespace App\Http\Resources;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomFieldDefinition
 */
class CustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'help_text' => $this->help_text,
            'placeholder' => $this->placeholder,
            'icon' => $this->icon,
            'group' => $this->group,
            'tab' => $this->tab,
            'sort_order' => $this->sort_order,
            'default_value' => $this->default_value,
            'config' => $this->config,
            'validation' => $this->validation,
            'relation_target' => $this->relation_target,
            'is_indexed' => $this->is_indexed,
            'is_active' => $this->is_active,
            'options' => $this->options->map(fn (CustomFieldOption $option): array => [
                'id' => $option->id,
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
                'icon' => $option->icon,
                'sort_order' => $option->sort_order,
                'is_default' => $option->is_default,
            ])->all(),
            'created_at' => $this->created_at,
        ];
    }
}
