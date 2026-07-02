<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends BaseModel
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'state_id',
        'province_id',
        'name',
        'country_code',
    ];

    protected $casts = [
        'country_id' => 'int',
        'state_id' => 'int',
        'province_id' => 'int',
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

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
