<?php

use App\Models\Attachment;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\User;
use Database\Seeders\DemoAttachmentSeeder;
use Database\Seeders\DemoCompanySiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keep generated binaries off the real disk.
    Storage::fake(config('attachments.disk'));
});

it('attaches avatars to some users and logos to some company sites via the real write path', function (): void {
    User::factory()->count(9)->create();
    City::factory()->count(10)->create();
    Company::factory()->count(4)->create();
    test()->seed(DemoCompanySiteSeeder::class);

    test()->seed(DemoAttachmentSeeder::class);

    $avatars = Attachment::query()->where('collection', User::AVATAR_COLLECTION)->get();
    $logos = Attachment::query()->where('collection', CompanySite::LOGO_COLLECTION)->get();

    expect($avatars->count())->toBeGreaterThanOrEqual(1);
    expect($logos->count())->toBeGreaterThanOrEqual(1);

    // The attachment is wired to a real owner via the enforced morph alias.
    expect($avatars->pluck('attachable_type')->unique()->all())->toBe(['user']);
    expect($logos->pluck('attachable_type')->unique()->all())->toBe(['company_site']);

    // The binary landed on the (faked) disk.
    $first = $avatars->first();
    Storage::disk($first->disk)->assertExists($first->path);

    // The owner surfaces the file through its relation.
    $ownerWithAvatar = User::query()->has('attachments')->firstOrFail();
    expect($ownerWithAvatar->avatar)->not->toBeNull();
});

it('is idempotent — re-running does not add a second avatar to a user', function (): void {
    User::factory()->count(9)->create();

    test()->seed(DemoAttachmentSeeder::class);
    $countBefore = Attachment::query()->where('collection', User::AVATAR_COLLECTION)->count();

    test()->seed(DemoAttachmentSeeder::class);

    expect(Attachment::query()->where('collection', User::AVATAR_COLLECTION)->count())->toBe($countBefore);
});
