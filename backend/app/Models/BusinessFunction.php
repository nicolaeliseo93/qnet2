<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\BusinessFunctionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Business function (spec 0010): a name, a mutually-exclusive type
 * (is_business_unit XOR is_business_service, both false meaning "neither"),
 * an optional manager and an optional set of associated users.
 */
#[Fillable(['name', 'is_business_unit', 'is_business_service', 'manager_id'])]
class BusinessFunction extends BaseModel
{
    /** @use HasFactory<BusinessFunctionFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_business_unit' => 'boolean',
            'is_business_service' => 'boolean',
            // Spec 0013 — external data migration: the source system's id for
            // a migrated business function, guarded (not in $fillable) so it
            // is only ever set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The function's single responsible user, if any.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The users associated to this function (0..n), via the pivot table.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_function_user');
    }
}
