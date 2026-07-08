<?php

namespace App\Enums;

use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Commercial agreement status of a Registry (spec 0020): where a
 * client/supplier stands in the negotiation lifecycle. Nullable column —
 * absence means the agreement dimension does not apply to that registry yet.
 */
enum AgreementStatusEnum: string
{
    use HasMeta;

    #[Label('Negotiating')]
    #[Icon('handshake')]
    #[IsDefault(true)]
    case Negotiating = 'negotiating';

    #[Label('Rejected')]
    #[Icon('x-circle')]
    case Rejected = 'rejected';

    #[Label('Agreed')]
    #[Icon('check-circle')]
    case Agreed = 'agreed';
}
