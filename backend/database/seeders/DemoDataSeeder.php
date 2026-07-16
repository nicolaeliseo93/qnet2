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
        $this->call(DemoReferentSeeder::class);
        $this->call(DemoSourceSeeder::class);
        $this->call(DemoSectorSeeder::class);
        $this->call(DemoTagSeeder::class);
        $this->call(DemoRolesSeeder::class);
        $this->call(DemoUsersSeeder::class);
        $this->call(DemoPersonalDataSeeder::class);
        $this->call(DemoUserContactSeeder::class);
        $this->call(DemoUserAddressSeeder::class);
        $this->call(DemoOperationalSiteSeeder::class);
        $this->call(DemoCompanySeeder::class);
        $this->call(DemoCompanySiteSeeder::class);
        $this->call(DemoBusinessFunctionSeeder::class);
        $this->call(DemoEmploymentProfileSeeder::class);
        $this->call(DemoProductCatalogSeeder::class);
        // Depends on sources/sectors/referents (lookups, seeded above) and
        // users (internal managers, seeded above) — must run after all of them.
        $this->call(DemoRegistrySeeder::class);
        $this->call(DemoPipelineStatusSeeder::class);
        // Depends on pipeline-statuses/registries/sources/business-functions/
        // product-categories/referents (lookups, all seeded above) and
        // `locations:add` (states, run by DatabaseSeeder) — must run after
        // all of them.
        $this->call(DemoProjectSeeder::class);
        // Depends on DemoProjectSeeder for the linked shape, plus the same
        // classification lookups for the standalone shape.
        $this->call(DemoCampaignSeeder::class);
        $this->call(DemoLeadStatusSeeder::class);
        // Depends on DemoReferentSeeder/DemoCampaignSeeder/DemoLeadStatusSeeder
        // (mandatory, BR-1/D-1) plus DemoOperationalSiteSeeder/DemoSourceSeeder/
        // DemoUsersSeeder (optional) — must run after all of them.
        $this->call(DemoLeadSeeder::class);
        $this->call(DemoNotificationSeeder::class);
        // Last: needs every entity's rows already seeded (it populates custom
        // field values on them) and companies for the relation target.
        // $this->call(DemoCustomFieldSeeder::class);
    }
}
