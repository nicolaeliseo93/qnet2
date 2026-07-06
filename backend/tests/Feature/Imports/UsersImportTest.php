<?php

use App\Enums\ImportStatus;
use App\Enums\PersonalDataTypeEnum;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * UsersImportDefinition, end-to-end through the real registered `users`
 * domain (config/imports.php).
 *
 * runValidateImportJob()/runProcessImportJob() are declared (unguarded) in
 * ValidateImportJobTest.php/ProcessImportJobTest.php and are globally
 * available across the Feature/Imports suite.
 */
it('dry-run validates individual/company rows + email/type/locale/role errors; commit creates users with random hashed passwords', function () {
    Storage::fake('local');

    Role::factory()->create(['name' => 'super-admin']);
    Role::factory()->create(['name' => 'editor']);
    User::factory()->create(['email' => 'existing@example.com']);

    // The importing actor is NOT a super-admin (no roles at all).
    $actor = User::factory()->create();

    $header = 'email,type,first_name,last_name,company_name,locale,is_active,roles';
    $csv = $header."\n"
        .'alice@example.com,individual,Alice,Wonder,,,true,editor'."\n" // valid: individual + role + explicit active
        .'bob@example.com,company,,,Bob Corp,it,,'."\n" // valid: company + explicit locale + blank is_active (-> active)
        .'ina@example.com,individual,Ina,Active,,,false,'."\n" // valid: individual + is_active false
        .',,X,Y,,,,'."\n" // invalid: email missing
        .'existing@example.com,,X,Y,,,,'."\n" // invalid: email duplicates an existing DB user
        .'nonames@example.com,individual,,,,,,'."\n" // invalid: individual missing first/last name
        .'nocompany@example.com,company,,,,,,'."\n" // invalid: company missing company_name
        .'badlocale@example.com,individual,A,B,,xx,,'."\n" // invalid: unknown locale
        .'badactive@example.com,individual,A,B,,,maybe,'."\n" // invalid: unparseable is_active
        .'escalate@example.com,individual,C,D,,,,super-admin'."\n"; // invalid: role not assignable by actor
    Storage::disk('local')->put('imports/users.csv', $csv);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'users',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/users.csv',
    ]);

    runValidateImportJob($run);

    $validated = $run->fresh();
    expect($validated->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($validated->total_rows)->toBe(10)
        ->and($validated->valid_rows)->toBe(3)
        ->and($validated->invalid_rows)->toBe(7)
        ->and(User::query()->count())->toBe(2); // dry-run created nothing (existing user + actor only)

    $reasons = collect($validated->preview['invalid_sample'])->pluck('errors')->flatten()->implode(' ');
    expect($reasons)->toContain('email is required')
        ->and($reasons)->toContain('already exists')
        ->and($reasons)->toContain('first_name is required when type is individual')
        ->and($reasons)->toContain('company_name is required when type is company')
        ->and($reasons)->toContain('locale must be one of')
        ->and($reasons)->toContain('is_active must be one of')
        ->and($reasons)->toContain('Role not assignable by the importing user: super-admin');

    $run->update(['status' => ImportStatus::Processing]);
    runProcessImportJob($run);

    $processed = $run->fresh();
    expect($processed->status)->toBe(ImportStatus::Completed)
        ->and($processed->imported_rows)->toBe(3);

    $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
    expect($alice->name)->toBe('Alice Wonder')
        ->and($alice->personalData->type)->toBe(PersonalDataTypeEnum::Individual)
        ->and($alice->is_active)->toBeTrue() // explicit `true` cell
        ->and($alice->hasRole('editor'))->toBeTrue()
        ->and($alice->hasRole('super-admin'))->toBeFalse();

    $bob = User::query()->where('email', 'bob@example.com')->firstOrFail();
    expect($bob->name)->toBe('Bob Corp')
        ->and($bob->personalData->type)->toBe(PersonalDataTypeEnum::Company)
        ->and($bob->locale)->toBe('it')
        ->and($bob->is_active)->toBeTrue(); // blank is_active defaults to active

    // is_active `false` is honored — the imported user is created inactive.
    $ina = User::query()->where('email', 'ina@example.com')->firstOrFail();
    expect($ina->is_active)->toBeFalse();

    // Passwords are random, hashed, never a plaintext/derived value from the
    // file (the CSV carries no password column at all), and distinct per user.
    expect($alice->password)->not->toBeEmpty()
        ->and($alice->password)->not->toBe($bob->password)
        ->and(Hash::check('password', $alice->password))->toBeFalse()
        ->and(Hash::check('alice@example.com', $alice->password))->toBeFalse();

    // SECURITY (AC): the escalated row is REJECTED outright — no user created.
    expect(User::query()->where('email', 'escalate@example.com')->exists())->toBeFalse();
});
