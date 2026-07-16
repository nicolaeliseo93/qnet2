<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoOpportunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds standalone opportunities, some carrying more than one ordered manager', function (): void {
    Registry::factory()->count(3)->create();
    Company::factory()->count(2)->create();
    CompanySite::factory()->count(2)->create();
    OperationalSite::factory()->count(2)->create();
    User::factory()->count(8)->create();

    test()->seed(DemoOpportunitySeeder::class);

    expect(Opportunity::count())->toBeGreaterThan(0);

    // At least one opportunity ends up with 2+ managers (the multi-slot path).
    $multiSlot = DB::table('opportunity_user')
        ->select('opportunity_id')
        ->groupBy('opportunity_id')
        ->havingRaw('COUNT(*) >= 2')
        ->get();

    expect($multiSlot)->not->toBeEmpty();

    // Positions are contiguous 1..n within a multi-slot deal (managerSyncMap).
    $opportunityId = $multiSlot->first()->opportunity_id;
    $positions = DB::table('opportunity_user')
        ->where('opportunity_id', $opportunityId)
        ->orderBy('position')
        ->pluck('position')
        ->all();

    expect($positions)->toBe(range(1, count($positions)));
});

it('assigns only real seeded users as managers', function (): void {
    Registry::factory()->count(2)->create();
    Company::factory()->count(2)->create();
    CompanySite::factory()->count(2)->create();
    OperationalSite::factory()->count(2)->create();
    User::factory()->count(6)->create();

    test()->seed(DemoOpportunitySeeder::class);

    $userIds = User::query()->pluck('id')->all();
    $managerIds = DB::table('opportunity_user')->pluck('user_id')->unique()->all();

    expect($managerIds)->not->toBeEmpty();

    foreach ($managerIds as $managerId) {
        expect($managerId)->toBeIn($userIds);
    }
});
