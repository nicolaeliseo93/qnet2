<?php

namespace App\Services;

use App\DataObjects\Companies\CreateCompanyData;
use App\DataObjects\Companies\UpdateCompanyData;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `companies` resource (spec 0010): create/update/
 * delete, including the single-address invariant delegated to AddressService
 * (createFor on the first write, update thereafter — never a second row).
 * The controller stays thin; this Service is the single authority.
 */
class CompanyService
{
    /**
     * Relations eager-loaded on every returned model, so CompanyResource never
     * N+1s while hydrating the address' geo names.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['addresses.country', 'addresses.state', 'addresses.province', 'addresses.city'];

    public function __construct(private readonly AddressService $addresses) {}

    public function create(User $actor, CreateCompanyData $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            /** @var Company $company */
            $company = Company::create($data->attributes());

            if ($data->hasAddress()) {
                $this->addresses->createFor($company, $data->address);
            }

            return $company->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(User $actor, Company $company, UpdateCompanyData $data): Company
    {
        return DB::transaction(function () use ($company, $data): Company {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $company->update($attributes);
            }

            if ($data->hasAddress()) {
                $this->writeAddress($company, $data);
            }

            return $company->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function delete(Company $company): void
    {
        // The owned address cascades away (HasAddresses::bootHasAddresses).
        $company->delete();
    }

    /**
     * A company owns AT MOST one address (invariant enforced here, not the
     * schema): update the existing row when there is one, else create it.
     */
    private function writeAddress(Company $company, UpdateCompanyData $data): void
    {
        $existing = $company->addresses()->first();

        if ($existing !== null) {
            $this->addresses->update($existing, $data->address);

            return;
        }

        $this->addresses->createFor($company, $data->address);
    }
}
