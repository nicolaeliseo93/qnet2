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

        $this->call(UserSeeder::class);
        $this->call(PersonalDataSeeder::class);
        $this->call(UserContactSeeder::class);
        $this->call(UserAddressSeeder::class);
        $this->call(OperationalSiteSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(BusinessFunctionSeeder::class);
        $this->call(EmploymentProfileSeeder::class);
        $this->call(NotificationSeeder::class);
    }
}
