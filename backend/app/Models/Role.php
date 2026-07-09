<?php

namespace App\Models;

use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Domain Role model.
 *
 * Like User (which extends Authenticatable), this is the framework-base
 * exception to the BaseModel rule: it must extend the Spatie base Role to keep
 * the package's role/permission wiring, while still honouring the org standards
 * — explicit $fillable (#[Fillable]) and activity-log via LogsModelActivity
 * (logs the fillable attributes, on dirty, log_name = "roles").
 *
 * Pointing config('permission.models.role') at this class makes every Spatie
 * relation (User->roles, syncRoles, getRoleNames) resolve to it transparently.
 */
#[Fillable(['name', 'guard_name', 'description'])]
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasCustomFields, HasFactory, LogsModelActivity;

    /**
     * This role's field-permission matrix rows (spec 0006) — per-resource,
     * per-field visible/editable/required restrictions within the code
     * ceiling. Fully replaced on every create/update (RoleService).
     */
    public function fieldPermissions(): HasMany
    {
        return $this->hasMany(RoleFieldPermission::class);
    }
}
