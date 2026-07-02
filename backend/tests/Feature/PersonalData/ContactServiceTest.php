<?php

use App\DataObjects\PersonalData\CreateContact;
use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ContactService::class);
    $this->card = PersonalData::factory()->create();
});

it('creates a contact for an owner', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email,
        value: 'ada@example.com',
        label: 'Work',
    ));

    expect($contact->contactable_id)->toBe($this->card->id)
        ->and($contact->contactable_type)->toBe($this->card->getMorphClass())
        ->and($contact->type)->toBe(ContactTypeEnum::Email);
});

it('keeps at most one primary contact per owner and type', function () {
    $first = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'a@example.com', isPrimary: true,
    ));
    $second = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'b@example.com', isPrimary: true,
    ));

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and(Contact::where('contactable_id', $this->card->id)
            ->where('type', ContactTypeEnum::Email->value)
            ->where('is_primary', true)->count())->toBe(1);
});

it('allows a primary contact per distinct type on the same owner', function () {
    $email = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'a@example.com', isPrimary: true,
    ));
    $phone = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 111 2222', isPrimary: true,
    ));

    expect($email->fresh()->is_primary)->toBeTrue()
        ->and($phone->fresh()->is_primary)->toBeTrue();
});

it('makePrimary promotes one contact and demotes its siblings of the same type', function () {
    $a = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 111 1111', isPrimary: true,
    ));
    $b = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 222 2222',
    ));

    $this->service->makePrimary($b);

    expect($b->fresh()->is_primary)->toBeTrue()
        ->and($a->fresh()->is_primary)->toBeFalse();
});

it('update edits a non-primary contact without touching siblings primary flag', function () {
    $primary = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'primary@example.com', isPrimary: true,
    ));
    $other = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'old@example.com',
    ));

    $updated = $this->service->update($other, new CreateContact(
        type: ContactTypeEnum::Email, value: 'new@example.com', label: 'Updated',
    ));

    expect($updated->value)->toBe('new@example.com')
        ->and($updated->label)->toBe('Updated')
        ->and($updated->fresh()->is_primary)->toBeFalse()
        ->and($primary->fresh()->is_primary)->toBeTrue();
});

it('update promotes a contact to primary and demotes the existing primary sibling', function () {
    $oldPrimary = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 111 1111', isPrimary: true,
    ));
    $candidate = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 222 2222',
    ));

    $updated = $this->service->update($candidate, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 222 2222', isPrimary: true,
    ));

    expect($updated->fresh()->is_primary)->toBeTrue()
        ->and($oldPrimary->fresh()->is_primary)->toBeFalse()
        ->and(Contact::where('contactable_id', $this->card->id)
            ->where('type', ContactTypeEnum::Phone->value)
            ->where('is_primary', true)->count())->toBe(1);
});

it('update keeps a contact primary and does not promote a sibling of another type', function () {
    $emailPrimary = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'a@example.com', isPrimary: true,
    ));
    $phonePrimary = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Phone, value: '+39 333 111 1111', isPrimary: true,
    ));

    $updated = $this->service->update($emailPrimary, new CreateContact(
        type: ContactTypeEnum::Email, value: 'a@example.com', label: 'Primary', isPrimary: true,
    ));

    expect($updated->fresh()->is_primary)->toBeTrue()
        ->and($phonePrimary->fresh()->is_primary)->toBeTrue()
        ->and($updated->label)->toBe('Primary');
});

it('update can demote a contact by passing isPrimary false (no auto re-promotion)', function () {
    $contact = $this->service->createFor($this->card, new CreateContact(
        type: ContactTypeEnum::Email, value: 'a@example.com', isPrimary: true,
    ));

    $updated = $this->service->update($contact, new CreateContact(
        type: ContactTypeEnum::Email, value: 'a@example.com', isPrimary: false,
    ));

    expect($updated->fresh()->is_primary)->toBeFalse()
        ->and(Contact::where('contactable_id', $this->card->id)
            ->where('type', ContactTypeEnum::Email->value)
            ->where('is_primary', true)->count())->toBe(0);
});
