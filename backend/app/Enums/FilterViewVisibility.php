<?php

namespace App\Enums;

/**
 * Visibility of a saved TableFilterView (spec 0007): who besides the owner can
 * see and apply it. Single source of truth for the allowed values, shared by
 * the model cast and TableFilterViewRequest's validation.
 */
enum FilterViewVisibility: string
{
    case Private = 'private';
    case Shared = 'shared';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
