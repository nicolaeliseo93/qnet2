<?php

namespace App\DataObjects\Enums;

/**
 * Presentation metadata for a single enum case, aggregated from the PHP
 * attributes declared on that case (Label, Color, Icon, IsDefault,
 * HiddenOnForm) by the App\Enums\Concerns\HasMeta reader.
 *
 * It is the serialization boundary towards the client: `toArray()` emits the
 * stable snake_case contract consumed by the API and by any inline `*_meta`
 * exposure. Pure PHP, no framework dependency.
 *
 * `label` is already resolved/translated by the reader; the remaining fields
 * mirror the optional attributes (null/false when the attribute is absent).
 */
final readonly class EnumMeta
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $color = null,
        public ?string $icon = null,
        public bool $isDefault = false,
        public bool $hiddenOnForm = false,
    ) {}

    /**
     * The serialized contract sent to the client. Keys are snake_case so the
     * frontend receives a stable shape regardless of the PHP property names.
     *
     * @return array{value: string, label: string, color: string|null, icon: string|null, is_default: bool, hidden_on_form: bool}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_default' => $this->isDefault,
            'hidden_on_form' => $this->hiddenOnForm,
        ];
    }
}
