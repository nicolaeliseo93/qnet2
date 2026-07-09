<?php

declare(strict_types=1);

namespace App\Tables\CustomFields;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every `TableDefinition` method `CustomFieldAwareTableDefinition` does NOT
 * augment: pure one-line delegation to the wrapped `$inner` definition. Split
 * out to keep the decorator within the file-size budget (engineering.md §6)
 * — the augmented methods (columns/resolveConfig/baseQuery/mapRow/the
 * allow-list + derived hooks) are what spec 0021 T6 is actually about.
 *
 * The using class must expose a `TableDefinition $inner` property (declared
 * there, e.g. as a `private readonly` promoted constructor property — NOT
 * redeclared here to avoid a readonly-modifier conflict with the trait).
 */
trait DelegatesUnaugmentedTableMethods
{
    public function domain(): string
    {
        return $this->inner->domain();
    }

    public function resource(): string
    {
        return $this->inner->resource();
    }

    public function modelClass(): string
    {
        return $this->inner->modelClass();
    }

    public function authorizeViewAny(User $actor): bool
    {
        return $this->inner->authorizeViewAny($actor);
    }

    public function filters(): array
    {
        return $this->inner->filters();
    }

    public function actions(): array
    {
        return $this->inner->actions();
    }

    public function defaultSort(): array
    {
        return $this->inner->defaultSort();
    }

    public function defaultPagination(): array
    {
        return $this->inner->defaultPagination();
    }

    public function actionsFor(User $actor, Model $row): array
    {
        return $this->inner->actionsFor($actor, $row);
    }

    public function deleteModel(Model $model): void
    {
        $this->inner->deleteModel($model);
    }
}
