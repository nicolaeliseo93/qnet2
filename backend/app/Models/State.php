<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\StateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends BaseModel
{
    /** @use HasFactory<StateFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'country_code',
    ];

    protected $casts = [
        'country_id' => 'int',
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

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
