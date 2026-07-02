<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Honorific / courtesy title for a natural person on a PersonalData card.
 * Optional (a card may have no title); string-backed so it is stored verbatim
 * and exposed unchanged to the client.
 */
enum PersonalTitleEnum: string
{
    use HasMeta;

    #[Label('Mr')]
    case Mr = 'mr';

    #[Label('Mrs')]
    case Mrs = 'mrs';

    #[Label('Ms')]
    case Ms = 'ms';

    #[Label('Dr')]
    case Dr = 'dr';

    #[Label('Prof')]
    case Prof = 'prof';

    /**
     * Resolve a stored/raw value to a title, returning null for anything
     * unknown or missing (the title is optional, so there is no default).
     */
    public static function fromValue(?string $value): ?self
    {
        return self::tryFrom($value ?? '');
    }
}
