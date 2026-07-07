<?php

namespace App\Services;

use App\DataObjects\CompanySites\CreateCompanySiteData;
use App\DataObjects\CompanySites\UpdateCompanySiteData;
use App\Enums\HttpStatusEnum;
use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `company-sites` resource (spec 0020): create/
 * update/delete/setDefault, delegating the single-address invariant to
 * AddressService and the banks diff to BankService — the controller stays
 * thin, this Service is the single authority.
 *
 * `default_bank_id` is resolved AFTER the bank sync (constraint: the FK cycle
 * default_bank_id <-> banks is broken by writing the site first, syncing its
 * banks, then re-validating default_bank_id against the resulting owned set —
 * all inside the same transaction, see resolveDefaultBank()).
 */
class CompanySiteService
{
    /**
     * Relations eager-loaded on every returned model, so CompanySiteResource
     * never N+1s while hydrating the address' geo names, the banks and the
     * responsible/company references.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = [
        'addresses.country', 'addresses.state', 'addresses.province', 'addresses.city',
        'banks',
        'responsibleRda', 'responsibleTickets', 'responsibleValidationContracts', 'responsibleValidationContractsTwo',
        'accountingManager', 'company',
    ];

    public function __construct(
        private readonly AddressService $addresses,
        private readonly BankService $banks,
        private readonly LogoService $logos,
    ) {}

    public function create(User $actor, CreateCompanySiteData $data): CompanySite
    {
        return DB::transaction(function () use ($data): CompanySite {
            /** @var CompanySite $companySite */
            $companySite = CompanySite::create($data->attributes());

            if ($data->hasAddress()) {
                $this->addresses->createFor($companySite, $data->address);
            }

            $this->banks->sync($companySite, $data->banks);
            $this->resolveDefaultBank($companySite, $data->defaultBankId, submitted: true);

            if ($data->hasLogo()) {
                $this->logos->set($companySite, $data->logo);
            }

            return $this->loadTree($companySite);
        });
    }

    public function update(User $actor, CompanySite $companySite, UpdateCompanySiteData $data): CompanySite
    {
        return DB::transaction(function () use ($companySite, $data): CompanySite {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $companySite->update($attributes);
            }

            if ($data->hasAddress()) {
                $this->writeAddress($companySite, $data);
            }

            if ($data->banksSubmitted) {
                $this->banks->sync($companySite, $data->banks);
            }

            if ($data->defaultBankIdSubmitted) {
                $this->resolveDefaultBank($companySite, $data->defaultBankId, submitted: true);
            }

            return $this->loadTree($companySite);
        });
    }

    /**
     * The owned address (HasAddresses), banks (FK cascade) and logo
     * (HasAttachments) all cascade away with the site.
     */
    public function delete(CompanySite $companySite): void
    {
        $companySite->delete();
    }

    /**
     * Exclusively promote $companySite to the default site: in one
     * transaction, demote every other site and set this one — an invariant
     * that cannot live in the schema (a boolean column has no "at most one
     * true" constraint), so it belongs here.
     */
    public function setDefault(CompanySite $companySite): void
    {
        DB::transaction(function () use ($companySite): void {
            CompanySite::query()
                ->where('is_default', true)
                ->whereKeyNot($companySite->id)
                ->update(['is_default' => false]);

            $companySite->update(['is_default' => true]);
        });
    }

    /**
     * Eager-load every relation CompanySiteResource reads, so the controller
     * never triggers a lazy load while building the response.
     */
    public function loadTree(CompanySite $companySite): CompanySite
    {
        return $companySite->fresh(self::HYDRATED_RELATIONS);
    }

    /**
     * A site owns AT MOST one address (invariant enforced here, not the
     * schema): update the existing row when there is one, else create it.
     */
    private function writeAddress(CompanySite $companySite, UpdateCompanySiteData $data): void
    {
        $existing = $companySite->addresses()->first();

        if ($existing !== null) {
            $this->addresses->update($existing, $data->address);

            return;
        }

        $this->addresses->createFor($companySite, $data->address);
    }

    /**
     * Resolve `default_bank_id` against the site's OWN banks, post-sync. A
     * value that does not belong to this site (e.g. another site's bank, or a
     * stale id from a bank just removed by the same request) is rejected —
     * StoreCompanySiteRequest/UpdateCompanySiteRequest already validate this
     * structurally against the submitted payload; this is the defence-in-depth
     * check against the actually persisted set.
     */
    private function resolveDefaultBank(CompanySite $companySite, ?int $defaultBankId, bool $submitted): void
    {
        if (! $submitted) {
            return;
        }

        if ($defaultBankId !== null && ! $companySite->banks()->whereKey($defaultBankId)->exists()) {
            abort(HttpStatusEnum::UNPROCESSABLE_ENTITY->value, 'The selected bank does not belong to this company site.');
        }

        $companySite->update(['default_bank_id' => $defaultBankId]);
    }
}
