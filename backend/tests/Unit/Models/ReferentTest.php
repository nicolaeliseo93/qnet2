<?php

use App\Enums\ReferentContactScopeEnum;
use App\Models\Concerns\HasPersonalData;
use App\Models\Concerns\LogsModelActivity;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\ReferentType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-007 — schema
// ---------------------------------------------------------------------------

it('creates the referents table with the expected columns', function () {
    expect(Schema::hasTable('referents'))->toBeTrue();
    expect(Schema::hasColumns('referents', [
        'id', 'name', 'referent_type_id', 'contact_scope', 'notes', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('name is required at the database level', function () {
    expect(fn () => DB::table('referents')->insert(['contact_scope' => 'internal', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('contact_scope defaults to internal', function () {
    DB::table('referents')->insert(['name' => 'Raw Insert', 'created_at' => now(), 'updated_at' => now()]);

    expect(DB::table('referents')->where('name', 'Raw Insert')->value('contact_scope'))->toBe('internal');
});

it('does NOT add any column to the shared personal_data/contacts/addresses tables', function () {
    expect(Schema::hasColumn('personal_data', 'referent_type_id'))->toBeFalse()
        ->and(Schema::hasColumn('contacts', 'referent_type_id'))->toBeFalse()
        ->and(Schema::hasColumn('addresses', 'referent_type_id'))->toBeFalse();
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_100100_create_referents_table.php');

    $migration->down();
    expect(Schema::hasTable('referents'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('referents'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-008 — model relations, casts and activity log
// ---------------------------------------------------------------------------

it('uses HasPersonalData: personalData() is a MorphOne to PersonalData', function () {
    $relation = (new Referent)->personalData();

    expect(class_uses(Referent::class))->toHaveKey(HasPersonalData::class);
    expect($relation)->toBeInstanceOf(MorphOne::class);
    expect($relation->getRelated())->toBeInstanceOf(PersonalData::class);
});

it('referentType() is a BelongsTo relation to ReferentType', function () {
    $relation = (new Referent)->referentType();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(ReferentType::class);
});

it('casts contact_scope to ReferentContactScopeEnum', function () {
    $referent = Referent::factory()->create(['contact_scope' => 'external']);

    expect($referent->contact_scope)->toBe(ReferentContactScopeEnum::External);
});

it('logs model activity on the referents log channel', function () {
    expect(class_uses(Referent::class))->toHaveKey(LogsModelActivity::class);
});

it('deleting a referent cascades its personal-data card (and the card its own contacts/addresses)', function () {
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->for($referent, 'personable')->create();

    $referent->delete();

    expect(PersonalData::find($card->id))->toBeNull();
});

it('the personable morph is stored using the registered morph alias, not the FQCN', function () {
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->for($referent, 'personable')->create();

    expect(DB::table('personal_data')->where('id', $card->id)->value('personable_type'))->toBe('referent');
});
