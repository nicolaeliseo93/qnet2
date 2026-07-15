<?php

namespace App\Imports\Leads;

use App\Enums\ContactTypeEnum;

/**
 * Single source of truth for the 3 mappable field ids the leads import wizard
 * treats as Referent contact channels (spec 0033 D-decision: "match su un
 * Referent esistente per email/telefono/cellulare"), shared by
 * LeadsImportDefinition::validateRow(), LeadDuplicateMatcher and
 * LeadProfileBuilder so the 3 places can never drift.
 */
final class LeadContactFields
{
    /**
     * @return array<string, ContactTypeEnum>
     */
    public static function map(): array
    {
        return [
            'email' => ContactTypeEnum::Email,
            'phone' => ContactTypeEnum::Phone,
            'mobile' => ContactTypeEnum::Mobile,
        ];
    }
}
