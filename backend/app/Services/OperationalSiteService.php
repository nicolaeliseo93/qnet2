<?php

namespace App\Services;

use App\DataObjects\OperationalSites\CreateOperationalSiteData;
use App\DataObjects\OperationalSites\UpdateOperationalSiteData;
use App\DataObjects\PersonalData\CreateAddress;
use App\Models\Address;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `operational-sites` resource (spec 0011): create/
 * update/delete, including the single-address invariant delegated to
 * AddressService (createFor on the first write, update thereafter — never a
 * second row), mirroring CompanyService.
 *
 * Unlike CompanyService's all-or-nothing nested `address` object, the write
 * payload here is FLAT and each field is independently `sometimes` (AC-011):
 * update() merges only the SUBMITTED fields onto the site's current primary
 * address (unsubmitted fields keep their persisted value), then calls
 * AddressService::update with the fully-merged CreateAddress — a full
 * overwrite is what AddressService expects, so the merge (not a partial
 * Eloquent update) is what makes the PATCH semantics truly partial.
 */
class OperationalSiteService
{
    /**
     * Relations eager-loaded on every returned model, so OperationalSiteResource
     * never N+1s while hydrating the address' geo names.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['addresses.country', 'addresses.state', 'addresses.province', 'addresses.city'];

    public function __construct(private readonly AddressService $addresses) {}

    public function create(User $actor, CreateOperationalSiteData $data): OperationalSite
    {
        return DB::transaction(function () use ($data): OperationalSite {
            /** @var OperationalSite $site */
            $site = OperationalSite::create([]);

            $this->addresses->createFor($site, $data->toAddress());

            return $site->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(User $actor, OperationalSite $site, UpdateOperationalSiteData $data): OperationalSite
    {
        return DB::transaction(function () use ($site, $data): OperationalSite {
            if ($data->hasAddressChanges()) {
                $this->writeAddress($site, $data);
            }

            return $site->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function delete(OperationalSite $site): void
    {
        // The owned address cascades away (HasAddresses::bootHasAddresses).
        $site->delete();
    }

    /**
     * Merge the SUBMITTED fields onto the site's current primary address
     * (falling back to its persisted value for anything not submitted), then
     * write it: update the existing row when there is one, else create it.
     */
    private function writeAddress(OperationalSite $site, UpdateOperationalSiteData $data): void
    {
        $existing = $site->addresses()->first();
        $merged = $this->mergeAddress($data, $existing);

        if ($existing !== null) {
            $this->addresses->update($existing, $merged);

            return;
        }

        $this->addresses->createFor($site, $merged);
    }

    /**
     * Build the FULL CreateAddress AddressService expects, from the submitted
     * fields layered onto $current's persisted values (or empty defaults when
     * there is no address yet). `isPrimary` is always preserved as the
     * existing value (defaulting to true for a brand-new address, since it
     * will be the site's only one) — never reset to CreateAddress' own
     * `false` default, which would silently demote the site's sole address.
     */
    private function mergeAddress(UpdateOperationalSiteData $data, ?Address $current): CreateAddress
    {
        return new CreateAddress(
            line1: $data->line1Submitted ? (string) $data->line1 : (string) ($current?->line1 ?? ''),
            postalCode: $data->postalCodeSubmitted ? $data->postalCode : $current?->postal_code,
            cityId: $data->cityIdSubmitted ? $data->cityId : $current?->city_id,
            provinceId: $data->provinceIdSubmitted ? $data->provinceId : $current?->province_id,
            stateId: $data->stateIdSubmitted ? $data->stateId : $current?->state_id,
            countryId: $data->countryIdSubmitted ? $data->countryId : $current?->country_id,
            isPrimary: $current?->is_primary ?? true,
        );
    }
}
