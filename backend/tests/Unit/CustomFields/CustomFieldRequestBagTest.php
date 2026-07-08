<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldRequestBag;

// spec 0021 — T7 write pipeline: the request-scoped holder populated by
// CaptureCustomFields and drained by HasCustomFields' saving/saved observers.

it('has() is false and values() is empty before anything is set', function () {
    $bag = new CustomFieldRequestBag;

    expect($bag->has())->toBeFalse()
        ->and($bag->values())->toBe([]);
});

it('set() makes has() true and values() return the same map', function () {
    $bag = new CustomFieldRequestBag;

    $bag->set(['notes' => 'hello']);

    expect($bag->has())->toBeTrue()
        ->and($bag->values())->toBe(['notes' => 'hello']);
});

it('an empty array set() counts as nothing pending', function () {
    $bag = new CustomFieldRequestBag;

    $bag->set([]);

    expect($bag->has())->toBeFalse();
});

it('pull() is destructive: it returns the values once, then the bag is empty', function () {
    $bag = new CustomFieldRequestBag;
    $bag->set(['notes' => 'hello']);

    $first = $bag->pull();
    $second = $bag->pull();

    expect($first)->toBe(['notes' => 'hello'])
        ->and($second)->toBe([])
        ->and($bag->has())->toBeFalse()
        ->and($bag->values())->toBe([]);
});

it('set() after a pull() re-arms the bag (a later request phase can still be captured)', function () {
    $bag = new CustomFieldRequestBag;
    $bag->set(['a' => 1]);
    $bag->pull();

    $bag->set(['b' => 2]);

    expect($bag->has())->toBeTrue()
        ->and($bag->values())->toBe(['b' => 2]);
});
