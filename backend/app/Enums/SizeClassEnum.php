<?php

namespace App\Enums;

use App\Enums\Attributes\Icon;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Size class of a Registry (spec 0020): coarse company-size bucket (micro,
 * small, medium, large), independent of `employee_count`. Nullable column, no
 * default case — absence means the classification has not been set yet.
 */
enum SizeClassEnum: string
{
    use HasMeta;

    #[Label('Micro')]
    #[Icon('circle-dot')]
    case Micro = 'micro';

    #[Label('Small')]
    #[Icon('circle')]
    case Small = 'small';

    #[Label('Medium')]
    #[Icon('circle-dashed')]
    case Medium = 'medium';

    #[Label('Large')]
    #[Icon('circle-off')]
    case Large = 'large';
}
