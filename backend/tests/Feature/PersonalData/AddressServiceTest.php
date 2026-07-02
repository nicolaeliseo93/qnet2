<?php

use App\DataObjects\PersonalData\CreateAddress;
use App\Models\Address;
use App\Models\PersonalData;
use App\Services\AddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AddressService::class);
    $this->card = PersonalData::factory()->create();
});

it('creates an address for an owner', function () {
    $address = $this->service->createFor($this->card, new CreateAddress(line1: '1 Loop'));

    expect($address->addressable_id)->toBe($this->card->id)
        ->and($address->addressable_type)->toBe($this->card->getMorphClass())
        ->and($address->line1)->toBe('1 Loop');
});

it('forces the first address of an owner to be primary', function () {
    $address = $this->service->createFor($this->card, new CreateAddress(line1: '1 Loop'));

    expect($address->fresh()->is_primary)->toBeTrue();
});

it('keeps a subsequent non-primary address non-primary and leaves the first primary', function () {
    $first = $this->service->createFor($this->card, new CreateAddress(line1: 'First'));
    $second = $this->service->createFor($this->card, new CreateAddress(line1: 'Second'));

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($second->fresh()->is_primary)->toBeFalse();
});

it('keeps at most one primary address per owner on create', function () {
    $first = $this->service->createFor($this->card, new CreateAddress(line1: 'First'));
    $second = $this->service->createFor($this->card, new CreateAddress(line1: 'Second', isPrimary: true));

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and(Address::where('addressable_id', $this->card->id)
            ->where('is_primary', true)->count())->toBe(1);
});

it('does not demote the primary of a different owner', function () {
    $otherCard = PersonalData::factory()->create();

    $mine = $this->service->createFor($this->card, new CreateAddress(line1: 'Mine'));
    $theirs = $this->service->createFor($otherCard, new CreateAddress(line1: 'Theirs'));

    expect($mine->fresh()->is_primary)->toBeTrue()
        ->and($theirs->fresh()->is_primary)->toBeTrue();
});

it('update promotes an address to primary and demotes the existing primary sibling', function () {
    $oldPrimary = $this->service->createFor($this->card, new CreateAddress(line1: 'Old'));
    $candidate = $this->service->createFor($this->card, new CreateAddress(line1: 'New'));

    $updated = $this->service->update($candidate, new CreateAddress(line1: 'New', isPrimary: true));

    expect($updated->fresh()->is_primary)->toBeTrue()
        ->and($oldPrimary->fresh()->is_primary)->toBeFalse()
        ->and(Address::where('addressable_id', $this->card->id)
            ->where('is_primary', true)->count())->toBe(1);
});

it('update does not promote a sibling of a different owner', function () {
    $otherCard = PersonalData::factory()->create();
    $theirsPrimary = $this->service->createFor($otherCard, new CreateAddress(line1: 'Theirs'));

    $mineFirst = $this->service->createFor($this->card, new CreateAddress(line1: 'Mine first'));
    $mineSecond = $this->service->createFor($this->card, new CreateAddress(line1: 'Mine second'));

    $this->service->update($mineSecond, new CreateAddress(line1: 'Mine second', isPrimary: true));

    expect($theirsPrimary->fresh()->is_primary)->toBeTrue()
        ->and($mineFirst->fresh()->is_primary)->toBeFalse()
        ->and($mineSecond->fresh()->is_primary)->toBeTrue();
});
