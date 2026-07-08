<?php

namespace App\Services;

use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\Registries\UpdateRegistryData;
use App\DataObjects\Users\ProfileData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic for the `registries` resource (spec 0020): create/update/
 * delete, mirroring ReferentService's nested personal-data write discipline
 * plus the business-specific relations (source, sectors, referents,
 * internal managers, supervisor/commercial/reporter) and the
 * is_qualified_supplier normalization.
 */
class RegistryService
{
    /**
     * Relations eager-loaded on the registry returned by create()/update()/
     * loadProfileTree() so the RegistryResource can emit the full nested tree
     * without a second request/N+1.
     *
     * @var array<int, string>
     */
    private const array WRITE_RESULT_RELATIONS = [
        'source',
        'supervisor',
        'commercial',
        'reporter',
        'sectors',
        'referents',
        'managers',
        'personalData.contacts',
        'personalData.addresses',
    ];

    public function __construct(private readonly RegistryProfileWriter $profileWriter) {}

    /**
     * Eager-load the nested read tree so a plain GET /registries/{registry}
     * returns the SAME shape create()/update() do. Single source of truth
     * for the relation set (WRITE_RESULT_RELATIONS).
     */
    public function loadProfileTree(Registry $registry): Registry
    {
        return $registry->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Create a new registry. `personal_data` is REQUIRED (it is the only
     * source of the derived `registries.name` — mirrors ReferentService::create).
     */
    public function create(User $actor, CreateRegistryData $data, ?ProfileData $profile): Registry
    {
        if ($profile === null) {
            // StoreRegistryRequest guarantees a profile (it is the only source
            // of the derived name); a null here is a programming error/bypass.
            throw new InvalidArgumentException('A personal-data profile is required to create a registry (its name is derived from the card).');
        }

        $registry = DB::transaction(function () use ($data, $profile): Registry {
            $attributes = $data->attributes();
            // `registries.name` is NOT NULL but the authoritative value is
            // derived by RegistryProfileWriter from the card (single
            // derivation point). Seed only a non-null placeholder here to
            // satisfy the constraint at INSERT.
            $attributes['name'] = '';

            $registry = Registry::create($attributes);

            $this->profileWriter->write($registry, $profile);
            $this->syncPivots($registry, $data);
            $this->normalizeQualifiedSupplier($registry);

            return $registry;
        });

        return $registry->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Update an existing registry. Only keys present in $data are touched, so
     * partial (PATCH) updates leave untouched fields as-is. When $profile is
     * provided, the card is upserted/synced in the same transaction; a null
     * $profile leaves the card untouched. Each pivot array is synced ONLY
     * when submitted (an omitted key leaves the current associations
     * untouched, an explicit empty array detaches all — mirrors
     * SectorService::update).
     */
    public function update(User $actor, Registry $registry, UpdateRegistryData $data, ?ProfileData $profile): Registry
    {
        $registry = DB::transaction(function () use ($registry, $data, $profile): Registry {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $registry->update($attributes);
            }

            $this->profileWriter->write($registry, $profile);
            $this->syncPivots($registry, $data);
            $this->normalizeQualifiedSupplier($registry);

            return $registry;
        });

        return $registry->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Delete the registry. Its personal-data card (and the card's own
     * contacts/addresses) cascade away via HasPersonalData; the 3 pivot
     * rows cascade away via their own cascadeOnDelete foreign keys.
     */
    public function delete(Registry $registry): void
    {
        $registry->delete();
    }

    /**
     * Sync the 3 to-many relations from the DTO's submitted id arrays, each
     * independently guarded by its own hasXxxIds() (mirrors
     * SectorService::create/update's `if ($data->hasTagIds())` guard): an
     * omitted key leaves that relation untouched, a submitted array
     * (including empty) is an authoritative sync.
     */
    private function syncPivots(Registry $registry, CreateRegistryData|UpdateRegistryData $data): void
    {
        if ($data->hasSectorIds()) {
            $registry->sectors()->sync($data->sectorIds);
        }

        if ($data->hasReferentIds()) {
            $registry->referents()->sync($data->referentIds);
        }

        if ($data->hasManagerIds()) {
            $registry->managers()->sync($data->managerIds);
        }
    }

    /**
     * Business rule (spec 0020): `is_qualified_supplier` is meaningless when
     * the registry is not a supplier — force it back to false whenever the
     * registry's EFFECTIVE (post-write) `is_supplier` is false, regardless of
     * whether this request touched either flag.
     */
    private function normalizeQualifiedSupplier(Registry $registry): void
    {
        if (! $registry->is_supplier && $registry->is_qualified_supplier) {
            $registry->update(['is_qualified_supplier' => false]);
        }
    }
}
