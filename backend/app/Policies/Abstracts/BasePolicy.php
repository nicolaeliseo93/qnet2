<?php

namespace App\Policies\Abstracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Standard CRUD policy backed by Spatie permissions.
 *
 * Each ability maps to a "{resource}.{ability}" permission, where {resource}
 * is the prefix returned by resource() (typically the model's table name).
 *
 * Override a single method in the concrete policy for non-standard rules
 * (e.g. ownership checks).
 */
abstract class BasePolicy
{
    /**
     * Permission prefix for this resource, e.g. "users".
     */
    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('viewAny'));
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission('view'));
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission('create'));
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission('update'));
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission('delete'));
    }

    /**
     * Bulk/file export of this resource (spec 0004 — centralized authorization
     * metadata: `resource.export`).
     */
    public function export(User $user): bool
    {
        return $user->can($this->permission('export'));
    }

    /**
     * Bulk/file import of this resource (spec 0004 — centralized authorization
     * metadata: `resource.import`).
     */
    public function import(User $user): bool
    {
        return $user->can($this->permission('import'));
    }

    /**
     * The standard CRUD (+export/import) abilities every resource policy
     * exposes.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'];
    }

    /**
     * The full list of standard permissions for this resource.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return array_map(fn (string $ability) => $this->permission($ability), self::abilities());
    }

    protected function permission(string $ability): string
    {
        return "{$this->resource()}.{$ability}";
    }
}
