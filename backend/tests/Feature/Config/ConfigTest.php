<?php

use App\Enums\ContactTypeEnum;
use App\Enums\NotificationLevelEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

it('is public: returns 200 without authentication', function () {
    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'OK');
});

it('exposes data.enums with the allowlisted snake_case keys', function () {
    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'enums' => [
                    'locale',
                    'personal_data_type',
                    'contact_type',
                    'notification_level',
                    'referent_contact_scope',
                ],
            ],
        ]);
});

it('exposes the supported locales with native, locale-independent labels', function () {
    // Native names so a language picker shows each language in its own name; they
    // are NOT translated by Accept-Language (unlike the other enum labels).
    $this->getJson('/api/config', ['Accept-Language' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.enums.locale', [
            ['value' => 'en', 'label' => 'English', 'color' => null, 'icon' => null, 'is_default' => true, 'hidden_on_form' => false],
            ['value' => 'it', 'label' => 'Italiano', 'color' => null, 'icon' => null, 'is_default' => false, 'hidden_on_form' => false],
        ]);
});

it('serializes every enum option with the six EnumMeta snake_case keys', function () {
    $option = $this->getJson('/api/config')
        ->assertOk()
        ->json('data.enums.notification_level.0');

    expect($option)->toHaveKeys([
        'value', 'label', 'color', 'icon', 'is_default', 'hidden_on_form',
    ]);

    expect($option)->toMatchArray([
        'value' => 'info',
        'label' => 'Info',
        'color' => 'blue',
        'icon' => 'info',
        'is_default' => true,
        'hidden_on_form' => false,
    ]);
});

it('preserves enum declaration order', function () {
    $values = collect(
        $this->getJson('/api/config')->assertOk()->json('data.enums.contact_type')
    )->pluck('value')->all();

    expect($values)->toBe(['phone', 'mobile', 'fax', 'email', 'pec', 'website']);
});

it('exposes the expected option count per enum', function () {
    $enums = $this->getJson('/api/config')->assertOk()->json('data.enums');

    expect($enums['locale'])->toHaveCount(2)
        ->and($enums['personal_data_type'])->toHaveCount(2)
        ->and($enums['contact_type'])->toHaveCount(6)
        ->and($enums['notification_level'])->toHaveCount(4)
        ->and($enums['referent_contact_scope'])->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// AC-018 (spec 0016) — referent_contact_scope options
// ---------------------------------------------------------------------------

it('exposes data.enums.referent_contact_scope with internal/external, internal default', function () {
    $options = $this->getJson('/api/config')->assertOk()->json('data.enums.referent_contact_scope');

    expect(collect($options)->pluck('value')->all())->toBe(['internal', 'external'])
        ->and(collect($options)->firstWhere('value', 'internal')['is_default'])->toBeTrue()
        ->and(collect($options)->firstWhere('value', 'external')['is_default'])->toBeFalse();
});

it('filters out cases flagged hiddenOnForm', function () {
    // No domain enum case is currently hiddenOnForm, so the contract is: every
    // option returned reports hidden_on_form === false and the count matches the
    // full set of cases (nothing dropped today, but the filter is exercised).
    $data = $this->getJson('/api/config')->assertOk()->json('data.enums.notification_level');

    expect(collect($data)->pluck('hidden_on_form')->unique()->all())->toBe([false])
        ->and($data)->toHaveCount(count(NotificationLevelEnum::cases()));
});

it('stays aligned with the in-memory options() filtered on hiddenOnForm', function () {
    $expected = collect(ContactTypeEnum::options())
        ->reject->hiddenOnForm
        ->map->toArray()
        ->values()
        ->all();

    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type', $expected);
});

it('localizes labels to the supported locale from Accept-Language', function () {
    $this->getJson('/api/config', ['Accept-Language' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type.0.label', 'Telefono');
});

it('honours a weighted/region Accept-Language header', function () {
    $this->getJson('/api/config', ['Accept-Language' => 'it-IT,it;q=0.9,en;q=0.8'])
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type.0.label', 'Telefono');
});

it('falls back to the app locale when Accept-Language is absent', function () {
    config(['app.locale' => 'en']);

    $this->getJson('/api/config')
        ->assertOk()
        ->assertJsonPath('data.enums.contact_type.0.label', 'Phone');
});

it('falls back to the app locale on an unsupported or malicious Accept-Language', function () {
    config(['app.locale' => 'en']);

    foreach (['fr', 'zz-ZZ', '../../etc/passwd', '<script>', 'de;q=1'] as $header) {
        $this->getJson('/api/config', ['Accept-Language' => $header])
            ->assertOk()
            ->assertJsonPath('data.enums.contact_type.0.label', 'Phone');
    }

    // The locale resolution must never leave the app in an unsupported state.
    expect(App::getLocale())->toBe('en');
});

it('is rate-limited (throttle headers present)', function () {
    $this->getJson('/api/config')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 30);
});

it('no longer exposes the removed per-enum endpoint', function () {
    $this->getJson('/api/enums/contact-type')->assertNotFound();
    $this->getJson('/api/enums/notification-level')->assertNotFound();
});
