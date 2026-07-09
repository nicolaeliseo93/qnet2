<?php

namespace App\Services;

use App\DataObjects\CompanySites\BankInput;
use App\DataObjects\CompanySites\CreateBank;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for a company site's bank list (spec 0020) — a real 1→N
 * child (FK `company_site_id`), unlike the polymorphic contacts/addresses.
 *
 * Owns the invariant "at most one primary (preferred) bank per site": there is
 * no `type` dimension (unlike a contact's per-type primary), and no auto-first
 * default (unlike an address) — the preferred bank is optional. Setting a bank
 * primary demotes every sibling, inside the transaction, so the site is never
 * left with two. The FK relation has no DB-level constraint for this, so it
 * lives here.
 */
class BankService
{
    public function create(CompanySite $companySite, CreateBank $data): CompanySiteBank
    {
        return DB::transaction(function () use ($companySite, $data): CompanySiteBank {
            /** @var CompanySiteBank $bank */
            $bank = $companySite->banks()->create($data->toAttributes());

            if ($bank->is_primary) {
                $this->demoteSiblings($bank);
            }

            return $bank;
        });
    }

    public function update(CompanySiteBank $bank, CreateBank $data): CompanySiteBank
    {
        return DB::transaction(function () use ($bank, $data): CompanySiteBank {
            $bank->update($data->toAttributes());

            if ($bank->is_primary) {
                $this->demoteSiblings($bank);
            }

            return $bank;
        });
    }

    public function delete(CompanySiteBank $bank): void
    {
        DB::transaction(fn () => $bank->delete());
    }

    /**
     * Authoritatively reconcile the site's banks against the submitted set
     * (spec 0020): a row whose id exists AND belongs to this site is updated;
     * any other row (no id, or an id that does not belong to this site) is
     * created; a currently owned bank not in the kept set is deleted. Runs in
     * its own transaction; the nested per-operation transactions become
     * savepoints — mirrors ContactService::sync/AddressService::sync.
     *
     * SECURITY: an id that does not belong to this site is treated as a
     * create, never an update — so a spoofed id can never mutate another
     * site's bank.
     *
     * @param  array<int, BankInput>  $inputs
     */
    public function sync(CompanySite $companySite, array $inputs): void
    {
        DB::transaction(function () use ($companySite, $inputs): void {
            /** @var array<int, CompanySiteBank> $owned */
            $owned = $companySite->banks()->get()->keyBy('id')->all();

            $keptIds = [];

            foreach ($inputs as $input) {
                $existing = $input->id !== null ? ($owned[$input->id] ?? null) : null;

                if ($existing !== null) {
                    $this->update($existing, $input->data);
                    $keptIds[] = $existing->id;

                    continue;
                }

                $created = $this->create($companySite, $input->data);
                $keptIds[] = $created->id;
            }

            foreach ($owned as $id => $bank) {
                if (! in_array($id, $keptIds, true)) {
                    $this->delete($bank);
                }
            }
        });
    }

    /**
     * Demote every other bank of the same site, leaving the given bank as the
     * only primary (preferred) one.
     */
    private function demoteSiblings(CompanySiteBank $bank): void
    {
        CompanySiteBank::query()
            ->where('company_site_id', $bank->company_site_id)
            ->where('is_primary', true)
            ->whereKeyNot($bank->getKey())
            ->update(['is_primary' => false]);
    }
}
