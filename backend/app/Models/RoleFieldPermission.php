<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single role's restriction row for one resource field (spec 0006):
 * whether the field is visible/editable/required for that role, WITHIN the
 * security ceiling computed by the resource's
 * AbstractResourceAuthorization::fieldPermissionCeiling(). The DB matrix can
 * only restrict the ceiling, never escalate it (enforced by the intersect in
 * AbstractResourceAuthorization::fieldPermissions()).
 *
 * No LogsModelActivity: the matrix is fully replaced on every role create/
 * update (RoleService::syncFieldPermissions), so the parent Role's own
 * activity log already captures "the role's field-permission matrix changed";
 * per-row audit would just be noise on every save.
 */
class RoleFieldPermission extends BaseModel
{
    protected $fillable = [
        'role_id',
        'resource',
        'field',
        'visible',
        'editable',
        'required',
    ];

    protected $casts = [
        'visible' => 'bool',
        'editable' => 'bool',
        'required' => 'bool',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
