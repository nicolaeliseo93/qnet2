<?php

namespace App\DataObjects\Table;

/**
 * One column's user-submitted presentation state for a table-preferences save
 * (POST /api/tables/{domain}/preferences).
 *
 * Declared DTO (no "magic flying array") so the FormRequest → Service contract is
 * explicit: the request builds these from the validated payload and the service
 * reads typed properties instead of `$column['id'] ?? null` — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * Only the user-overridable presentation properties are carried (ADR-0004).
 * A null property means the user did NOT submit that property for this column
 * (a submitted value is always a real bool/int — never null), so it is left at
 * its definition default when computing the sparse delta.
 */
final readonly class ColumnState
{
    public function __construct(
        public string $id,
        public ?bool $visible = null,
        public ?int $width = null,
        public ?int $order = null,
    ) {}

    /**
     * Build from one validated `columns.*` entry of TablePreferencesRequest.
     *
     * @param  array<string, mixed>  $column
     */
    public static function fromValidated(array $column): self
    {
        return new self(
            id: (string) $column['id'],
            visible: array_key_exists('visible', $column) ? (bool) $column['visible'] : null,
            width: array_key_exists('width', $column) ? (int) $column['width'] : null,
            order: array_key_exists('order', $column) ? (int) $column['order'] : null,
        );
    }
}
