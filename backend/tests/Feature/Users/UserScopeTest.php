<?php

use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('selects only users that own a personal-data card', function () {
    $withCard = User::factory()->create();
    PersonalData::factory()->individual()->for($withCard, 'personable')->create();

    $withoutCard = User::factory()->create();

    $result = User::withPersonalData()->get();

    expect($result->pluck('id')->all())
        ->toContain($withCard->id)
        ->not->toContain($withoutCard->id);
});

it('returns no users when none own a personal-data card', function () {
    User::factory()->count(3)->create();

    expect(User::withPersonalData()->get())->toBeEmpty();
});
