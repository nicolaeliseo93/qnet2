<?php

use App\Enums\ContactTypeEnum;
use App\Models\Address;
use App\Models\Concerns\HasAddresses;
use App\Models\Concerns\HasContacts;
use App\Models\Concerns\HasPersonalData;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Fake soft-deletable owner used only to exercise the soft-delete guard inside
 * the cleanup traits: on a soft delete the owner still exists, so its
 * contacts/addresses/card must be preserved; on a force delete they cascade.
 * No production model uses SoftDeletes, hence this in-test owner.
 */
class SoftDeletableOwner extends Model
{
    use HasAddresses, HasContacts, HasPersonalData, SoftDeletes;

    protected $table = 'soft_deletable_owners';

    protected $guarded = [];

    /**
     * The app enforces a morph map (AppServiceProvider), so this test-only model
     * — which is a polymorphic owner — must expose a stable alias of its own
     * rather than letting Eloquent fall back to the (forbidden) FQCN.
     */
    public function getMorphClass(): string
    {
        return 'soft_deletable_owner';
    }
}

it('HasPersonalData: a user owns at most one card via morphOne', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create();

    expect($user->personalData)->toBeInstanceOf(PersonalData::class)
        ->and($user->personalData->id)->toBe($card->id);
});

it('HasContacts: primaryContact returns the preferred channel of a type', function () {
    $card = PersonalData::factory()->create();
    Contact::factory()->email()->for($card, 'contactable')->create();
    $primary = Contact::factory()->email()->primary()->for($card, 'contactable')->create();

    expect($card->primaryContact(ContactTypeEnum::Email)->id)->toBe($primary->id)
        ->and($card->primaryContact(ContactTypeEnum::Phone))->toBeNull();
});

it('deleting a card cascades: its contacts and addresses are removed (no orphans)', function () {
    $card = PersonalData::factory()->create();
    Contact::factory()->count(2)->for($card, 'contactable')->create();
    Address::factory()->count(2)->for($card, 'addressable')->create();

    $card->delete();

    expect(Contact::where('contactable_id', $card->id)->count())->toBe(0)
        ->and(Address::where('addressable_id', $card->id)->count())->toBe(0);
});

it('deleting the owning user cascades the card and its nested contacts/addresses', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create();
    Contact::factory()->count(2)->for($card, 'contactable')->create();
    Address::factory()->count(1)->for($card, 'addressable')->create();

    $user->delete();

    expect(PersonalData::where('personable_id', $user->id)->count())->toBe(0)
        ->and(Contact::where('contactable_id', $card->id)->count())->toBe(0)
        ->and(Address::where('addressable_id', $card->id)->count())->toBe(0);
});

describe('cleanup traits with a soft-deletable owner', function () {
    beforeEach(function () {
        Schema::create('soft_deletable_owners', function ($table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
        });
    });

    afterEach(function () {
        Schema::dropIfExists('soft_deletable_owners');
    });

    it('preserves contacts, addresses and card when the owner is soft-deleted', function () {
        $owner = SoftDeletableOwner::create();
        $card = PersonalData::factory()->for($owner, 'personable')->create();
        Contact::factory()->count(2)->for($owner, 'contactable')->create();
        Address::factory()->for($owner, 'addressable')->create();

        $owner->delete(); // soft delete: owner row still exists

        expect($owner->trashed())->toBeTrue()
            ->and(Contact::where('contactable_id', $owner->id)->count())->toBe(2)
            ->and(Address::where('addressable_id', $owner->id)->count())->toBe(1)
            ->and(PersonalData::where('personable_id', $owner->id)->count())->toBe(1)
            ->and($card->fresh())->not->toBeNull();
    });

    it('cascades contacts, addresses and card when the owner is force-deleted', function () {
        $owner = SoftDeletableOwner::create();
        PersonalData::factory()->for($owner, 'personable')->create();
        Contact::factory()->count(2)->for($owner, 'contactable')->create();
        Address::factory()->for($owner, 'addressable')->create();

        $owner->forceDelete();

        expect(Contact::where('contactable_id', $owner->id)->count())->toBe(0)
            ->and(Address::where('addressable_id', $owner->id)->count())->toBe(0)
            ->and(PersonalData::where('personable_id', $owner->id)->count())->toBe(0);
    });
});
