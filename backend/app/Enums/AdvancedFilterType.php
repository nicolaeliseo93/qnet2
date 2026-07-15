<?php

namespace App\Enums;

/**
 * The 17 widget types supported by the backend-driven advanced-filter panel
 * (spec 0032, second level above the AG Grid column filters). Each descriptor
 * in a domain's `advancedFilters()` catalog declares exactly one: the type
 * drives both the frontend field renderer and — server-side — the value
 * shape/operator `AdvancedFilterApplier` accepts and applies. Single source
 * of truth for the allowed values, shared by the descriptor schema and
 * `TableRowsRequest`'s (and its persistence twins') validation.
 */
enum AdvancedFilterType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case NumberRange = 'number_range';
    case Date = 'date';
    case DateRange = 'date_range';
    case Datetime = 'datetime';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Autocomplete = 'autocomplete';
    case AutocompleteMulti = 'autocomplete_multi';
    case Checkbox = 'checkbox';
    case Switch = 'switch';
    case Radio = 'radio';
    case Enum = 'enum';
    case Relation = 'relation';
    case AsyncSearch = 'async_search';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
