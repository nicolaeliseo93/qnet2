<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Kind of person a PersonalData card describes: a natural person (individual)
 * or a legal entity (company). Single source of truth for the allowed values,
 * shared by the model cast, the factory and the per-type validation.
 */
enum PersonalDataTypeEnum: string
{
    use HasMeta;

    #[Label('Individual')]
    #[Icon('user')]
    #[Color('blue')]
    #[IsDefault(true)]
    case Individual = 'individual';

    #[Label('Company')]
    #[Icon('building')]
    #[Color('violet')]
    case Company = 'company';

    /**
     * Resolve a stored/raw value to a type, defaulting to Individual for
     * anything unknown or missing — so the domain never holds an
     * out-of-contract type.
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Individual;
    }
}
