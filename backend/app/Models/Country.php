<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LocalizesGeoName;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends BaseModel
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    use LocalizesGeoName;

    protected $fillable = [
        'iso2',
        'name',
        'status',
        'phone_code',
        'iso3',
        'region',
        'subregion',
    ];

    protected $casts = [
        'iso2' => 'string',
        'name' => 'string',
        'status' => 'int',
        'phone_code' => 'string',
        'iso3' => 'string',
        'region' => 'string',
        'subregion' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }
}
