<?php

use App\Models\Address;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\Concerns\LogsModelActivity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the company_sites table with the expected columns', function () {
    expect(Schema::hasTable('company_sites'))->toBeTrue();
    expect(Schema::hasColumns('company_sites', [
        'id', 'old_id', 'name', 'email', 'fiscal_code', 'vat_number', 'phone', 'pec', 'fax', 'notes', 'is_default',
        'responsible_rda_id', 'responsible_tickets_id', 'responsible_validation_contracts_id',
        'responsible_validation_contracts_two_id', 'default_bank_id', 'proforma_progressive', 'invoice_progressive',
        'quotation_layout_id', 'quotation_header_id', 'quotation_footer_id',
        'company_id', 'accounting_manager_id', 'store_id', 'company_type', 'commissions', 'order_sites',
        'payment_status_assign_technician', 'payment_status_deposit', 'payment_status_balance',
        'default_payment_id', 'default_vat_id', 'status', 'color', 'surface_sqm', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('creates the company_site_banks table with the expected columns and cascade FK', function () {
    expect(Schema::hasTable('company_site_banks'))->toBeTrue();
    expect(Schema::hasColumns('company_site_banks', ['id', 'old_id', 'company_site_id', 'name', 'iban', 'notes', 'created_at', 'updated_at']))->toBeTrue();
});

it('name/email are required at the database level', function () {
    expect(fn () => DB::table('company_sites')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('old_id is unique among company_sites when present', function () {
    CompanySite::factory()->create(['old_id' => 42]);

    expect(fn () => DB::table('company_sites')->insert([
        'old_id' => 42, 'name' => 'Dup', 'email' => 'dup@example.test', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('down() reverses both migrations, up() recreates them (in FK order)', function () {
    $banks = require database_path('migrations/2026_07_07_100100_create_company_site_banks_table.php');
    $sites = require database_path('migrations/2026_07_07_100000_create_company_sites_table.php');

    $banks->down();
    expect(Schema::hasTable('company_site_banks'))->toBeFalse();

    $sites->down();
    expect(Schema::hasTable('company_sites'))->toBeFalse();

    $sites->up();
    $banks->up();
    expect(Schema::hasTable('company_sites'))->toBeTrue()
        ->and(Schema::hasTable('company_site_banks'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations, morph alias, cascade delete
// ---------------------------------------------------------------------------

it('uses the "company_site" morph alias (enforced morphMap)', function () {
    expect((new CompanySite)->getMorphClass())->toBe('company_site');
});

it('addresses() is a polymorphic morphMany to Address', function () {
    $companySite = CompanySite::factory()->create();
    $address = Address::factory()->primary()->for($companySite, 'addressable')->create();

    expect($companySite->addresses)->toHaveCount(1)
        ->and($companySite->addresses->first()->is($address))->toBeTrue();
});

it('banks() is a real hasMany to CompanySiteBank', function () {
    $companySite = CompanySite::factory()->create();
    $bank = CompanySiteBank::factory()->for($companySite)->create();

    expect($companySite->banks)->toHaveCount(1)
        ->and($companySite->banks->first()->is($bank))->toBeTrue();
});

it('defaultBank() resolves the chosen bank', function () {
    $companySite = CompanySite::factory()->create();
    $bank = CompanySiteBank::factory()->for($companySite)->create();
    $companySite->update(['default_bank_id' => $bank->id]);

    expect($companySite->fresh()->defaultBank->is($bank))->toBeTrue();
});

it('the 4 responsible relations and company()/accountingManager() are BelongsTo(User)/(Company)', function () {
    $rda = User::factory()->create();
    $tickets = User::factory()->create();
    $validation = User::factory()->create();
    $validationTwo = User::factory()->create();
    $accountingManager = User::factory()->create();
    $company = Company::factory()->create();

    $companySite = CompanySite::factory()->create([
        'responsible_rda_id' => $rda->id,
        'responsible_tickets_id' => $tickets->id,
        'responsible_validation_contracts_id' => $validation->id,
        'responsible_validation_contracts_two_id' => $validationTwo->id,
        'accounting_manager_id' => $accountingManager->id,
        'company_id' => $company->id,
    ]);

    expect($companySite->responsibleRda->is($rda))->toBeTrue()
        ->and($companySite->responsibleTickets->is($tickets))->toBeTrue()
        ->and($companySite->responsibleValidationContracts->is($validation))->toBeTrue()
        ->and($companySite->responsibleValidationContractsTwo->is($validationTwo))->toBeTrue()
        ->and($companySite->accountingManager->is($accountingManager))->toBeTrue()
        ->and($companySite->company->is($company))->toBeTrue();
});

it('deleting a site cascades its address, banks and logo', function () {
    Storage::fake('local');
    $companySite = CompanySite::factory()->create();
    $address = Address::factory()->primary()->for($companySite, 'addressable')->create();
    $bank = CompanySiteBank::factory()->for($companySite)->create();
    $companySite->attach(UploadedFile::fake()->image('logo.png'), CompanySite::LOGO_COLLECTION);
    $attachmentId = $companySite->fresh()->logo->id;

    $companySite->delete();

    expect(Address::find($address->id))->toBeNull()
        ->and(CompanySiteBank::find($bank->id))->toBeNull()
        ->and(Attachment::find($attachmentId))->toBeNull();
});

it('primaryAddress resolves the is_primary row, falling back to any owned row', function () {
    $companySite = CompanySite::factory()->create();
    $address = Address::factory()->primary()->for($companySite, 'addressable')->create();

    expect($companySite->primaryAddress?->is($address))->toBeTrue();
});

it('logo()/logoDataUri() mirror the avatar pattern', function () {
    Storage::fake('local');
    $companySite = CompanySite::factory()->create();

    expect($companySite->logoDataUri())->toBeNull();

    $companySite->attach(UploadedFile::fake()->image('logo.png'), CompanySite::LOGO_COLLECTION);
    $companySite->refresh();

    expect($companySite->logo)->not->toBeNull()
        ->and($companySite->logoDataUri())->toStartWith('data:image/');
});

it('logs model activity on the company_sites log channel', function () {
    expect(class_uses(CompanySite::class))->toHaveKey(LogsModelActivity::class);
});

it('factory withAddress()/default() states work', function () {
    $companySite = CompanySite::factory()->withAddress()->default()->create();

    expect($companySite->addresses()->where('is_primary', true)->count())->toBe(1)
        ->and($companySite->is_default)->toBeTrue();
});
