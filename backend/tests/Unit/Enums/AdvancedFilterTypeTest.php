<?php

use App\Enums\AdvancedFilterType;

it('exposes exactly the 17 widget types declared in spec 0032', function () {
    expect(AdvancedFilterType::values())->toBe([
        'text', 'textarea', 'number', 'number_range', 'date', 'date_range', 'datetime',
        'select', 'multiselect', 'autocomplete', 'autocomplete_multi', 'checkbox',
        'switch', 'radio', 'enum', 'relation', 'async_search',
    ])->and(AdvancedFilterType::values())->toHaveCount(17);
});
