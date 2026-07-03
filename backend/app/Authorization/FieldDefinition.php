<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Static catalogue entry for a single resource field: its key, the form
 * `type` hint the frontend uses to pick a control, and an optional display
 * `group`. Emitted by `GET /api/meta/{resource}` (create-context skeleton)
 * as `data.fields`, alongside the per-actor `permissions.fields` computed
 * from the same key set by `ResourceAuthorization::fieldPermissions()`.
 */
final class FieldDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly ?string $group = null,
    ) {}

    /**
     * @return array{key: string, type: string, group: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'group' => $this->group,
        ];
    }
}
