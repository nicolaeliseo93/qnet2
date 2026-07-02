<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * UI/user locales the application ships translations for. Single source of truth
 * for the supported set, shared by:
 *  - the PUBLIC bootstrap endpoint (GET /api/config → enums.locale), so the
 *    frontend renders the language select from the backend instead of hardcoding
 *    the list/labels;
 *  - the request validation (Store/UpdateUserRequest, UpdateProfileRequest) via
 *    {@see values()};
 *  - the users table `locale` column/filter options.
 *
 * Labels are the NATIVE language names ("English", "Italiano") on purpose: a
 * language picker shows each language in its own name, so they are intentionally
 * locale-independent (the #[Label] strings are not translation keys, so __()
 * returns them verbatim regardless of the request Accept-Language).
 */
enum LocaleEnum: string
{
    use HasMeta;

    #[Label('English')]
    #[IsDefault(true)]
    case En = 'en';

    #[Label('Italiano')]
    case It = 'it';

    /**
     * The supported locale values (the `value` of every case), for validation
     * rules and option lists. Declaration order, so `en` (the app default) first.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
