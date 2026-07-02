<?php

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('attach(): stores and links a file to the owner in one call', function () {
    $owner = User::factory()->create();

    $attachment = $owner->attach(
        UploadedFile::fake()->create('contract.pdf', 32, 'application/pdf'),
        'contracts'
    );

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->attachable_type)->toBe($owner->getMorphClass())
        ->and($attachment->attachable_id)->toBe($owner->id)
        ->and($attachment->collection)->toBe('contracts');

    expect($owner->attachments()->count())->toBe(1);
    Storage::disk('local')->assertExists($attachment->path);
});

it('attach(): records the authenticated user as uploader', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    $attachment = $owner->attach(UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'));

    expect($attachment->uploaded_by)->toBe($owner->id);
});

it('attach(): works without an authenticated user (console/queue context)', function () {
    $owner = User::factory()->create();

    $attachment = $owner->attach(UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'));

    expect($attachment->uploaded_by)->toBeNull()
        ->and($attachment->attachable_id)->toBe($owner->id);
});

it('deleting the owner cascades: removes attachment rows and their binaries', function () {
    $owner = User::factory()->create();
    $owner->attach(UploadedFile::fake()->create('one.pdf', 8, 'application/pdf'));
    $owner->attach(UploadedFile::fake()->create('two.pdf', 8, 'application/pdf'));

    $paths = $owner->attachments()->pluck('path');
    expect($paths)->toHaveCount(2);

    $owner->delete();

    expect(Attachment::where('attachable_id', $owner->id)->count())->toBe(0);
    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
});
