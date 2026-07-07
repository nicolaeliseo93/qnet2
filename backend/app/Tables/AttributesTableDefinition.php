<?php

namespace App\Tables;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\User;
use App\Tables\Attributes\AttributeColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `attributes` domain (spec 0017).
 *
 * `code`/`name`/`created_at` are real DB columns handled entirely by the
 * generic engine, mirroring ReferentTypesTableDefinition. `data_type` is
 * ALSO a real column but rendered as a badge (AttributeType) — badgesFor/
 * enumKeyFor are overridden (mirroring UsersTableDefinition's `user_type`
 * column) and so is distinctValues(): the generic engine's fallback resolves
 * distinct values through the Eloquent builder, which hydrates the backed
 * enum cast and then fails to stringify it — bypassed here via a plain
 * DB::table query on the raw column, mirroring the derived-column pattern
 * used elsewhere for a different reason (no cast involved there).
 */
class AttributesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'attributes';
    }

    /**
     * @return class-string<Attribute>
     */
    public function modelClass(): string
    {
        return Attribute::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives AttributePolicy::viewAny
    // from modelClass() (attributes.viewAny).

    /**
     * @return Builder<Attribute>
     */
    public function baseQuery(): Builder
    {
        // options_count rides along in mapRow() only — not part of the
        // frozen column contract, no filter/sort ever targets it.
        return Attribute::query()->withCount('options');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return AttributeColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return AttributeColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return AttributeColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Badge metadata for the `data_type` column, driven by AttributeType.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'data_type') {
            return null;
        }

        return array_map(static fn ($meta): array => $meta->toArray(), AttributeType::options());
    }

    /**
     * The `data_type` badge is driven by AttributeType, exposed to the
     * frontend config under the `attribute_type` enum key (config/config.php
     * → form_enums), so the frontend can localize the badge label.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'data_type' ? 'attribute_type' : null;
    }

    /**
     * Map an Attribute to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Attribute $row */
        return [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'data_type' => $row->data_type,
            'options_count' => (int) $row->options_count,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via AttributePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        return $allowed;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for `data_type`: a plain
     * DB::table query on the raw column (never through Eloquent, which would
     * hydrate the AttributeType cast and fail to stringify it).
     *
     * @param  Builder<Attribute>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== 'data_type') {
            return null;
        }

        $attributeIds = (clone $query)->select('attributes.id');

        return DB::table('attributes')
            ->whereIn('id', $attributeIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('data_type', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('data_type')
            ->limit($limit)
            ->pluck('data_type')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
