<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;
use App\Models\Lead;

/**
 * Display-only lifecycle status for a Lead. It is derived from existing lead
 * state and never stored: a generated opportunity wins over assignment, then
 * an operator assignment, then the unassociated fallback.
 */
enum LeadLifecycleStatus: string
{
    use HasMeta;

    #[Label('Not associated')]
    #[Color('slate')]
    case NotAssociated = 'not_associated';

    #[Label('Associated')]
    #[Color('blue')]
    case Associated = 'associated';

    #[Label('Converted to opportunity')]
    #[Color('green')]
    case ConvertedToOpportunity = 'converted_to_opportunity';

    public static function forLead(Lead $lead): self
    {
        $attributes = $lead->getAttributes();

        if (array_key_exists('opportunity_exists', $attributes)) {
            return (bool) $attributes['opportunity_exists']
                ? self::ConvertedToOpportunity
                : self::fromAssignment($lead);
        }

        if ($lead->relationLoaded('opportunity')) {
            return $lead->opportunity === null
                ? self::fromAssignment($lead)
                : self::ConvertedToOpportunity;
        }

        return $lead->opportunity()->exists()
            ? self::ConvertedToOpportunity
            : self::fromAssignment($lead);
    }

    private static function fromAssignment(Lead $lead): self
    {
        return $lead->operator_id === null ? self::NotAssociated : self::Associated;
    }
}
