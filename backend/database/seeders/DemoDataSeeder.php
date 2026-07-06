<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Fill every table with fake fixtures for local development and demos. It first
 * runs the default seed (reference data, roles/permissions and the demo user)
 * so it is self-contained on a fresh database, then layers the generated users
 * and their related records on top. Run on demand:
 * `php artisan db:seed --class=DemoDataSeeder`.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $this->call(DemoReferentTypeSeeder::class);
        $this->call(DemoRolesSeeder::class);
        $this->call(DemoUsersSeeder::class);
        $this->call(DemoPersonalDataSeeder::class);
        $this->call(DemoUserContactSeeder::class);
        $this->call(DemoUserAddressSeeder::class);
        $this->call(DemoOperationalSiteSeeder::class);
        $this->call(DemoCompanySeeder::class);
        $this->call(DemoBusinessFunctionSeeder::class);
        $this->call(DemoEmploymentProfileSeeder::class);
        $this->call(DemoNotificationSeeder::class);
    }
}
