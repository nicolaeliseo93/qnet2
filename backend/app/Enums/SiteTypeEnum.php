<?php

namespace App\Enums;

use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Site type of an address (spec 0020): classifies WHICH kind of location an
 * address represents (legal seat, delivery point, billing address, operational
 * site). Shared column on the polymorphic `addresses` table — every owner
 * (Users/Referents/Companies/Registries) gets the DB default `billing` unless
 * it opts into the select via `showSiteType` (Registries form only).
 */
enum SiteTypeEnum: string
{
    use HasMeta;

    #[Label('Legal seat')]
    #[Icon('landmark')]
    case LegalSeat = 'legal_seat';

    #[Label('Delivery')]
    #[Icon('truck')]
    case Delivery = 'delivery';

    #[Label('Billing')]
    #[Icon('receipt')]
    #[IsDefault(true)]
    case Billing = 'billing';

    #[Label('Operational site')]
    #[Icon('factory')]
    case OperationalSite = 'operational_site';
}
