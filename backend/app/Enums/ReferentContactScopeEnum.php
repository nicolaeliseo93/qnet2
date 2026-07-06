<?php

namespace App\Enums;

use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Contact scope of a Referent (spec 0016): whether the referent is an
 * internal (company staff) or external (third-party) point of contact.
 * Single source of truth for the allowed values, shared by the model cast,
 * the factory and the FormRequest validation (Rule::enum).
 */
enum ReferentContactScopeEnum: string
{
    use HasMeta;

    #[Label('Internal')]
    #[Icon('building-2')]
    #[IsDefault(true)]
    case Internal = 'internal';

    #[Label('External')]
    #[Icon('handshake')]
    case External = 'external';
}
