<?php

namespace App\Services;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for personal-data cards.
 *
 * Centralizes create/update of the single card an owner holds (morphOne), so
 * controllers and models stay thin. Operations run in a transaction; the
 * one-card-per-owner rule is honored by updating the existing card instead of
 * creating a second one.
 */
class PersonalDataService
{
    /**
     * Create the personal-data card for an owner that does not have one yet.
     */
    public function createFor(Model $owner, CreatePersonalData $data): PersonalData
    {
        return DB::transaction(
            fn (): PersonalData => $owner->personalData()->create($data->toAttributes())
        );
    }

    /**
     * Create or replace the owner's single card (idempotent on the
     * one-card-per-owner invariant): updates it in place when it already exists.
     */
    public function upsertFor(Model $owner, CreatePersonalData $data): PersonalData
    {
        return DB::transaction(function () use ($owner, $data): PersonalData {
            /** @var PersonalData|null $existing */
            $existing = $owner->personalData()->first();

            if ($existing !== null) {
                $existing->update($data->toAttributes());

                return $existing;
            }

            return $owner->personalData()->create($data->toAttributes());
        });
    }

    /**
     * Update an existing personal-data card.
     */
    public function update(PersonalData $card, CreatePersonalData $data): PersonalData
    {
        return DB::transaction(function () use ($card, $data): PersonalData {
            $card->update($data->toAttributes());

            return $card;
        });
    }

    /**
     * Delete a personal-data card. Its owned contacts and addresses cascade
     * away via the card's HasContacts/HasAddresses traits, so no orphan rows
     * remain (the morph relations have no DB-level foreign key).
     */
    public function delete(PersonalData $card): void
    {
        DB::transaction(fn () => $card->delete());
    }
}
