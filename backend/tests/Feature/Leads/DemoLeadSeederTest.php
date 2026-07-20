<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\DemoLeadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds leads through the real LeadService, each with a valid registry/campaign', function (): void {
    Registry::factory()->count(5)->create();
    Campaign::factory()->count(5)->create();
    OperationalSite::factory()->count(3)->create();
    Source::factory()->count(3)->create();
    User::factory()->count(3)->create();

    $this->seed(DemoLeadSeeder::class);

    $leads = Lead::query()->with(['registry', 'campaign'])->get();

    expect($leads)->not->toBeEmpty();

    $leads->each(function (Lead $lead): void {
        expect($lead->registry)->not->toBeNull()
            ->and($lead->campaign)->not->toBeNull();
    });
});

it('is a no-op when there are no registries or campaigns to seed against', function (): void {
    $this->seed(DemoLeadSeeder::class);

    expect(Lead::query()->count())->toBe(0);
});

it('is idempotent: re-running clears and recreates instead of duplicating', function (): void {
    Registry::factory()->count(5)->create();
    Campaign::factory()->count(5)->create();

    $this->seed(DemoLeadSeeder::class);
    $firstCount = Lead::query()->count();

    $this->seed(DemoLeadSeeder::class);

    expect(Lead::query()->count())->toBe($firstCount);
});
