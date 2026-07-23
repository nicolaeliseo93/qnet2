<?php

declare(strict_types=1);

namespace App\DataObjects\RequestManagement;

use App\DataObjects\Users\ProfileData;

/**
 * Validated payload for POST /api/request-management (spec 0057): the
 * client anagraphic block is EXACTLY one of the two mutually-exclusive
 * branches (D-2) — `registryId` (an existing Registry) or `clientProfile` (a
 * brand-new one, built from the submitted `client_identity`/`client_contacts`/
 * `client_address`) — StoreRequestRequest's own rules already enforce the XOR,
 * so exactly one of the two is non-null here. `productLines` is always
 * present (D-3, at least one row).
 */
final readonly class CreateRequestData
{
    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines
     */
    public function __construct(
        public ?int $registryId,
        public ?ProfileData $clientProfile,
        public array $productLines,
    ) {}
}
