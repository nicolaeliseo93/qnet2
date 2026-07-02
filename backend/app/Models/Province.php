<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The province geo level, between a state (region) and a city. Read-only
 * reference data (like Country / State / City): no Policy, no activity log, no
 * per-resource permission. Carries its ancestor ids (country_id, state_id)
 * following the same denormalized-key pattern as State.
 */
class Province extends BaseModel
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'state_id',
        'name',
        'country_code',
    ];

    protected $casts = [
        'country_id' => 'int',
        'state_id' => 'int',
        'name' => 'string',
        'country_code' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
