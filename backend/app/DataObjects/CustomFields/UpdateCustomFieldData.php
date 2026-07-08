<?php

namespace App\DataObjects\CustomFields;

/**
 * Validated payload for a partial (PATCH) custom field definition update
 * (PUT/PATCH /api/custom-fields/{customField}, spec 0021). `description`,
 * `help_text`, `placeholder`, `icon`, `group`, `tab`, `config`, `validation`
 * and `relation_target` are all legitimately nullable VALUES (clearing them
 * is a valid PATCH), so a plain null property cannot distinguish "not
 * submitted" from "submitted as null" — the `*Submitted` flags carry that
 * distinction explicitly, mirroring UpdateProductCategoryData. `entity_type`/
 * `type`/`key` are applied by the Service AFTER its immutability guard, not
 * folded into submittedAttributes(). `options`, when submitted, is a
 * FULL-REPLACE of the definition's option list.
 */
final readonly class UpdateCustomFieldData
{
    /**
     * @param  array<string, mixed>|null  $config
     * @param  array<string, mixed>|null  $validation
     * @param  array<string, mixed>|null  $relationTarget
     * @param  array<int, array{value: string, label: string, color?: string|null, icon?: string|null, sort_order?: int, is_default?: bool}>|null  $options
     */
    public function __construct(
        public ?string $entityType = null,
        public ?string $key = null,
        public ?string $type = null,
        public ?string $label = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $helpText = null,
        public bool $helpTextSubmitted = false,
        public ?string $placeholder = null,
        public bool $placeholderSubmitted = false,
        public ?string $icon = null,
        public bool $iconSubmitted = false,
        public ?string $group = null,
        public bool $groupSubmitted = false,
        public ?string $tab = null,
        public bool $tabSubmitted = false,
        public ?int $sortOrder = null,
        public ?array $config = null,
        public bool $configSubmitted = false,
        public ?array $validation = null,
        public bool $validationSubmitted = false,
        public ?array $relationTarget = null,
        public bool $relationTargetSubmitted = false,
        public ?bool $isIndexed = null,
        public ?bool $isActive = null,
        public ?array $options = null,
    ) {}

    /**
     * Build from the validated UpdateCustomFieldRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            entityType: array_key_exists('entity_type', $data) ? (string) $data['entity_type'] : null,
            key: array_key_exists('key', $data) ? (string) $data['key'] : null,
            type: array_key_exists('type', $data) ? (string) $data['type'] : null,
            label: array_key_exists('label', $data) ? (string) $data['label'] : null,
            description: $data['description'] ?? null,
            descriptionSubmitted: array_key_exists('description', $data),
            helpText: $data['help_text'] ?? null,
            helpTextSubmitted: array_key_exists('help_text', $data),
            placeholder: $data['placeholder'] ?? null,
            placeholderSubmitted: array_key_exists('placeholder', $data),
            icon: $data['icon'] ?? null,
            iconSubmitted: array_key_exists('icon', $data),
            group: $data['group'] ?? null,
            groupSubmitted: array_key_exists('group', $data),
            tab: $data['tab'] ?? null,
            tabSubmitted: array_key_exists('tab', $data),
            sortOrder: array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : null,
            config: $data['config'] ?? null,
            configSubmitted: array_key_exists('config', $data),
            validation: $data['validation'] ?? null,
            validationSubmitted: array_key_exists('validation', $data),
            relationTarget: $data['relation_target'] ?? null,
            relationTargetSubmitted: array_key_exists('relation_target', $data),
            isIndexed: array_key_exists('is_indexed', $data) ? (bool) $data['is_indexed'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            options: array_key_exists('options', $data) ? (array) $data['options'] : null,
        );
    }

    public function hasEntityType(): bool
    {
        return $this->entityType !== null;
    }

    public function hasKey(): bool
    {
        return $this->key !== null;
    }

    public function hasType(): bool
    {
        return $this->type !== null;
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update. `entity_type`/`type`/`key` are
     * applied by the Service AFTER the immutability guard; `options` is
     * full-replace synced separately.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->label !== null) {
            $attributes['label'] = $this->label;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->helpTextSubmitted) {
            $attributes['help_text'] = $this->helpText;
        }

        if ($this->placeholderSubmitted) {
            $attributes['placeholder'] = $this->placeholder;
        }

        if ($this->iconSubmitted) {
            $attributes['icon'] = $this->icon;
        }

        if ($this->groupSubmitted) {
            $attributes['group'] = $this->group;
        }

        if ($this->tabSubmitted) {
            $attributes['tab'] = $this->tab;
        }

        if ($this->sortOrder !== null) {
            $attributes['sort_order'] = $this->sortOrder;
        }

        if ($this->configSubmitted) {
            $attributes['config'] = $this->config;
        }

        if ($this->validationSubmitted) {
            $attributes['validation'] = $this->validation;
        }

        if ($this->relationTargetSubmitted) {
            $attributes['relation_target'] = $this->relationTarget;
        }

        if ($this->isIndexed !== null) {
            $attributes['is_indexed'] = $this->isIndexed;
        }

        if ($this->isActive !== null) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }
}
