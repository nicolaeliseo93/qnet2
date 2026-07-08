<?php

namespace App\Models;

use App\Enums\SiteTypeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reusable, polymorphic address.
 *
 * Attach to any owning model with a morph relation, e.g.:
 *
 *     public function addresses(): MorphMany
 *     {
 *         return $this->morphMany(Address::class, 'addressable');
 *     }
 */
class Address extends BaseModel
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory, LogsModelActivity;

    protected $fillable = [
        'line1',
        'line2',
        'postal_code',
        'site_type',
        'city_id',
        'state_id',
        'province_id',
        'country_id',
        'latitude',
        'longitude',
        'is_primary',
    ];

    protected $casts = [
        'line1' => 'string',
        'line2' => 'string',
        'postal_code' => 'string',
        'site_type' => SiteTypeEnum::class,
        'city_id' => 'int',
        'state_id' => 'int',
        'province_id' => 'int',
        'country_id' => 'int',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_primary' => 'bool',
    ];

    /**
     * The locating parts of an address (street lines and exact coordinates) are
     * personal data and not needed for a readable audit trail. Hiding them keeps
     * them out of the activity log (LogsModelActivity excludes $hidden) and out
     * of default JSON serialization; postal code and the city/province/
     * state/country ids stay logged and serializable. The values remain readable via
     * the attribute and through an explicit, authorized resource when one is
     * added.
     *
     * @var list<string>
     */
    protected $hidden = [
        'line1',
        'line2',
        'latitude',
        'longitude',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
