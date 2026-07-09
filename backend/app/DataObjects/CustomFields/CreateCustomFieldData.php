<?php

namespace App\DataObjects\CustomFields;

/**
 * Validated payload for creating a custom field definition (POST
 * /api/custom-fields, spec 0021 — ADMIN CRUD DEFINIZIONI). Declared DTO (no
 * "magic flying array") so the StoreCustomFieldRequest → CustomFieldService
 * contract is explicit, mirroring CreateAttributeData.
 *
 * `options` is null when the client did not submit the key at all (a
 * non-ENUM field never carries options).
 */
final readonly class CreateCustomFieldData
{
    /**
     * @param  array<string, mixed>|null  $config
     * @param  array<string, mixed>|null  $validation
     * @param  array<string, mixed>|null  $relationTarget
     * @param  array<int, array{value: string, label: string, color?: string|null, icon?: string|null, sort_order?: int, is_default?: bool}>|null  $options
     */
    public function __construct(
        public string $entityType,
        public string $key,
        public string $type,
        public string $label,
        public ?string $description,
        public ?string $helpText,
        public ?string $placeholder,
        public ?string $icon,
        public ?string $group,
        public ?string $tab,
        public int $sortOrder,
        public ?array $config,
        public ?array $validation,
        public ?array $relationTarget,
        public bool $isIndexed,
        public bool $isActive,
        public ?array $options,
    ) {}

    /**
     * Build from the validated StoreCustomFieldRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            entityType: (string) $data['entity_type'],
            key: (string) $data['key'],
            type: (string) $data['type'],
            label: (string) $data['label'],
            description: $data['description'] ?? null,
            helpText: $data['help_text'] ?? null,
            placeholder: $data['placeholder'] ?? null,
            icon: $data['icon'] ?? null,
            group: $data['group'] ?? null,
            tab: $data['tab'] ?? null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
            config: $data['config'] ?? null,
            validation: $data['validation'] ?? null,
            relationTarget: $data['relation_target'] ?? null,
            isIndexed: (bool) ($data['is_indexed'] ?? false),
            isActive: (bool) ($data['is_active'] ?? true),
            options: array_key_exists('options', $data) ? (array) $data['options'] : null,
        );
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }
}
