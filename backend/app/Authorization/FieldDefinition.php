<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Static catalogue entry for a single resource field: its key, the form
 * `type` hint the frontend uses to pick a control, and an optional display
 * `group`. Emitted by `GET /api/meta/{resource}` (create-context skeleton)
 * as `data.fields`, alongside the per-actor `permissions.fields` computed
 * from the same key set by `ResourceAuthorization::fieldPermissions()`.
 *
 * `mandatory` (spec 0008) marks a field vital to creating the resource: the
 * Role field-permission matrix locks its checkboxes (visible/editable/required
 * forced on, disabled) and the server-side merge refuses to let any
 * `role_field_permissions` row narrow it below the ceiling.
 */
final class FieldDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly ?string $group = null,
        public readonly bool $mandatory = false,
    ) {}

    /**
     * @return array{key: string, type: string, group: string|null, mandatory: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'group' => $this->group,
            'mandatory' => $this->mandatory,
        ];
    }
}
