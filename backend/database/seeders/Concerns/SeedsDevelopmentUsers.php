<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait SeedsDevelopmentUsers
{
    /**
     * Canonical demo account kept for local development.
     */
    private const string DEMO_EMAIL = 'demo@app.com';

    /**
     * Deterministic domain used by the development user seed.
     */
    private const string SEEDED_EMAIL_DOMAIN = 'example.test';

    private function seededUsersQuery(): Builder
    {
        return User::query()->where(function (Builder $query): void {
            $query->where('email', self::DEMO_EMAIL)
                ->orWhere('email', 'like', '%@'.self::SEEDED_EMAIL_DOMAIN);
        });
    }
}
