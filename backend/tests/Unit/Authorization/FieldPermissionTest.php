<?php

use App\Authorization\FieldPermission;

/*
|--------------------------------------------------------------------------
| Named constructors — every factory, all six serialized flags
|--------------------------------------------------------------------------
|
| Pure value object, no Laravel app needed. visibleEditable()/visibleReadonly()
| are already exercised indirectly through the Users/Roles authorization
| feature tests; hidden()/disabled() are not, so they are covered directly
| here alongside the other two for a complete, explicit factory matrix.
*/

it('visibleEditable(): visible + editable, required forwarded, hidden/readonly derived false', function () {
    expect(FieldPermission::visibleEditable(required: true)->toArray())->toBe([
        'visible' => true,
        'hidden' => false,
        'editable' => true,
        'readonly' => false,
        'required' => true,
        'disabled' => false,
    ]);

    expect(FieldPermission::visibleEditable()->toArray()['required'])->toBeFalse();
});

it('visibleReadonly(): visible but not editable — readonly derived true', function () {
    expect(FieldPermission::visibleReadonly()->toArray())->toBe([
        'visible' => true,
        'hidden' => false,
        'editable' => false,
        'readonly' => true,
        'required' => false,
        'disabled' => false,
    ]);
});

it('hidden(): not visible — hidden derived true, readonly derived false', function () {
    $permission = FieldPermission::hidden();

    expect($permission->toArray())->toBe([
        'visible' => false,
        'hidden' => true,
        'editable' => false,
        'readonly' => false,
        'required' => false,
        'disabled' => false,
    ]);
});

it('disabled(): visible but hard-disabled — readonly derived false (disabled overrides it)', function () {
    $permission = FieldPermission::disabled();

    expect($permission->toArray())->toBe([
        'visible' => true,
        'hidden' => false,
        'editable' => false,
        'readonly' => false,
        'required' => false,
        'disabled' => true,
    ]);
});

it('derivation rules hold across every factory: hidden = !visible, readonly = visible && !editable && !disabled', function () {
    $permissions = [
        FieldPermission::visibleEditable(),
        FieldPermission::visibleEditable(required: true),
        FieldPermission::visibleReadonly(),
        FieldPermission::hidden(),
        FieldPermission::disabled(),
    ];

    foreach ($permissions as $permission) {
        expect($permission->hidden)->toBe(! $permission->visible)
            ->and($permission->readonly)->toBe($permission->visible && ! $permission->editable && ! $permission->disabled);
    }
});
