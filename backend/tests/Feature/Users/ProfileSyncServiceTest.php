<?php

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Services\AddressService;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->contacts = app(ContactService::class);
    $this->addresses = app(AddressService::class);
    $this->card = PersonalData::factory()->create();
});

it('ContactService::sync creates new contacts when no id is given', function () {
    $this->contacts->sync($this->card, [
        new ContactInput(null, new CreateContact(ContactTypeEnum::Email, 'a@example.com')),
        new ContactInput(null, new CreateContact(ContactTypeEnum::Phone, '+39 333 111 2222')),
    ]);

    expect($this->card->contacts()->count())->toBe(2);
});

it('ContactService::sync updates an owned contact by id and deletes the omitted', function () {
    $keep = Contact::factory()->email()->for($this->card, 'contactable')->create();
    $drop = Contact::factory()->email()->for($this->card, 'contactable')->create();

    $this->contacts->sync($this->card, [
        new ContactInput($keep->id, new CreateContact(ContactTypeEnum::Email, 'updated@example.com')),
    ]);

    expect($keep->fresh()->value)->toBe('updated@example.com')
        ->and(Contact::find($drop->id))->toBeNull();
});

it('ContactService::sync with an empty array deletes all owned contacts', function () {
    Contact::factory()->email()->for($this->card, 'contactable')->create();
    Contact::factory()->for($this->card, 'contactable')->create();

    $this->contacts->sync($this->card, []);

    expect($this->card->contacts()->count())->toBe(0);
});

it('ContactService::sync treats a foreign id as a create, never touching the other card', function () {
    $otherCard = PersonalData::factory()->create();
    $foreign = Contact::factory()->email()->for($otherCard, 'contactable')->create(['value' => 'foreign@example.com']);

    $this->contacts->sync($this->card, [
        new ContactInput($foreign->id, new CreateContact(ContactTypeEnum::Email, 'mine@example.com')),
    ]);

    expect($foreign->fresh()->value)->toBe('foreign@example.com')
        ->and($this->card->contacts()->where('value', 'mine@example.com')->exists())->toBeTrue();
});

it('ContactService::sync keeps a single primary per type across a batch', function () {
    $this->contacts->sync($this->card, [
        new ContactInput(null, new CreateContact(ContactTypeEnum::Email, 'a@example.com', isPrimary: true)),
        new ContactInput(null, new CreateContact(ContactTypeEnum::Email, 'b@example.com', isPrimary: true)),
    ]);

    expect($this->card->contacts()->where('type', 'email')->where('is_primary', true)->count())->toBe(1);
});

it('AddressService::sync keeps a single primary across a batch', function () {
    $this->addresses->sync($this->card, [
        new AddressInput(null, new CreateAddress(line1: 'A', isPrimary: true)),
        new AddressInput(null, new CreateAddress(line1: 'B', isPrimary: true)),
    ]);

    expect($this->card->addresses()->count())->toBe(2)
        ->and($this->card->addresses()->where('is_primary', true)->count())->toBe(1);
});

it('AddressService::sync deletes omitted addresses by diff', function () {
    $this->addresses->sync($this->card, [
        new AddressInput(null, new CreateAddress(line1: 'First')),
    ]);
    $first = $this->card->addresses()->firstOrFail();

    $this->addresses->sync($this->card, [
        new AddressInput($first->id, new CreateAddress(line1: 'First updated')),
        new AddressInput(null, new CreateAddress(line1: 'Second')),
    ]);

    expect($this->card->addresses()->count())->toBe(2)
        ->and($first->fresh()->line1)->toBe('First updated');
});
