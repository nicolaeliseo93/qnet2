<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Data type of a dynamic product attribute (spec 0017): drives both the
 * value_* column an attribute's values are routed into
 * (ProductAttributeValue) and the form control the frontend renders. ENUM is
 * the only type backed by a discrete option list (AttributeOption).
 *
 * Single source of truth for the allowed values, shared by the model cast,
 * the FormRequest rule and the client select (config/config.php form_enums →
 * `attribute_type`). Point of extension for future types.
 */
enum AttributeType: string
{
    use HasMeta;

    #[Label('String')]
    #[IsDefault(true)]
    case String = 'STRING';

    #[Label('Integer')]
    case Integer = 'INTEGER';

    #[Label('Decimal')]
    case Decimal = 'DECIMAL';

    #[Label('Boolean')]
    case Boolean = 'BOOLEAN';

    #[Label('Enum')]
    case Enum = 'ENUM';
}
