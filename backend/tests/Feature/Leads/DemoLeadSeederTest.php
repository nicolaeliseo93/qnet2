<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\DemoLeadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds leads through the real LeadService, each with a valid registry/campaign/lead_status', function (): void {
    Registry::factory()->count(5)->create();
    Campaign::factory()->count(5)->create();
    LeadStatus::factory()->count(3)->create();
    OperationalSite::factory()->count(3)->create();
    Source::factory()->count(3)->create();
    User::factory()->count(3)->create();

    $this->seed(DemoLeadSeeder::class);

    $leads = Lead::query()->with(['registry', 'campaign', 'leadStatus'])->get();

    expect($leads)->not->toBeEmpty();

    $leads->each(function (Lead $lead): void {
        expect($lead->registry)->not->toBeNull()
            ->and($lead->campaign)->not->toBeNull()
            ->and($lead->leadStatus)->not->toBeNull();
    });
});

it('is a no-op when there are no registries, campaigns or lead statuses to seed against', function (): void {
    $this->seed(DemoLeadSeeder::class);

    expect(Lead::query()->count())->toBe(0);
});

it('seeds leads using the always-present system statuses when no custom lead status exists (spec 0039 D-2)', function (): void {
    // requirement changed (spec 0039, D-2): the "no lead statuses to seed
    // against" case (spec 0029 D-1) is no longer reachable — the
    // system-status migration seeds the 2 mandatory rows ("Nuovo"/"Chiuso")
    // unconditionally, so `lead_statuses` is never empty. The seeder now
    // proceeds and round-robins leads against those 2 system rows.
    Registry::factory()->count(5)->create();
    Campaign::factory()->count(5)->create();

    $this->seed(DemoLeadSeeder::class);

    $leads = Lead::query()->with('leadStatus')->get();

    expect($leads)->not->toBeEmpty();
    $leads->each(fn (Lead $lead) => expect($lead->leadStatus?->system_key)->not->toBeNull());
});

it('is idempotent: re-running clears and recreates instead of duplicating', function (): void {
    Registry::factory()->count(5)->create();
    Campaign::factory()->count(5)->create();
    LeadStatus::factory()->count(3)->create();

    $this->seed(DemoLeadSeeder::class);
    $firstCount = Lead::query()->count();

    $this->seed(DemoLeadSeeder::class);

    expect(Lead::query()->count())->toBe($firstCount);
});
