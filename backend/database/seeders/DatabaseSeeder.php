<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Artisan::call('locations:add');

        $this->call(RolePermissionSeeder::class);
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
