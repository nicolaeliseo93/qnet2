<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Row projection for the `request-management` domain (spec 0049): turns an
 * eager-loaded Opportunity into the operative row payload the grid renders.
 *
 * Split out of RequestManagementTableDefinition so the definition keeps a
 * single concern (query building: scoping, filters, sorts, distinct values)
 * and the per-row presentation lives here. Every value is resolved from
 * relations already loaded by the definition's baseQuery — this mapper never
 * queries.
 */
final class RequestRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(Opportunity $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            // The only related-row column, always projected WITH its color
            // token for the working-state badge.
            'workflow_status' => $this->summarizeWithColor($row->workflowStatus),
            // "Categoria prodotto": aggregated product categories.
            'product_categories' => $this->summarizeNames($row->productLines->pluck('productCategory')),
            // "Operatore": the Account Manager at pivot position 2 (GA2).
            'operator_ga2' => $this->operatorSummary($row->managers),
            ...$this->clientAnagraphics($row),
            // "Prossimo richiamo" (spec 0052 D-1/D-5), same wire format as
            // RequestManagementResource so FE date parsing stays identical.
            'next_callback_at' => $row->next_callback_at?->format('Y-m-d\TH:i'),
            // Hidden column, drives the default "recently worked first" sort only.
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * The client anagraphic columns, read from the Registry's PersonalData
     * card (phone = its primary phone/mobile contact).
     *
     * @return array<string, string|null>
     */
    private function clientAnagraphics(Opportunity $row): array
    {
        $card = $row->registry?->personalData;

        return [
            'first_name' => $card?->first_name,
            'last_name' => $card?->last_name,
            'tax_code' => $card?->tax_code,
            'phone' => $this->primaryPhone($card?->contacts),
        ];
    }

    /**
     * The GA2 operator as a person summary (id, name, inline avatar) for the
     * shared UserCell — the Account Manager attached at pivot `position` =
     * Opportunity::OPERATOR_MANAGER_POSITION, or null when that slot is empty.
     * Mirrors OpportunitiesTableDefinition::userSummary (supervisor column).
     *
     * @param  Collection<int, User>  $managers
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function operatorSummary(Collection $managers): ?array
    {
        $operator = $managers->first(
            static fn (User $manager): bool => (int) $manager->pivot->position === Opportunity::OPERATOR_MANAGER_POSITION,
        );

        if ($operator === null) {
            return null;
        }

        return ['id' => $operator->id, 'name' => $operator->name, 'avatar_url' => $operator->avatarDataUri()];
    }

    /**
     * The client's primary phone number: the first primary contact of a
     * telephone kind (phone or mobile) on the card, or null.
     *
     * @param  Collection<int, Contact>|null  $contacts
     */
    private function primaryPhone(?Collection $contacts): ?string
    {
        $phone = $contacts?->first(static fn (Contact $contact): bool => $contact->is_primary
            && in_array($contact->type, [ContactTypeEnum::Phone, ContactTypeEnum::Mobile], true));

        return $phone?->value;
    }

    /**
     * A related row projected WITH its `color` token, so the grid renders the
     * colored working-state badge — a generic summarize() would drop it
     * (mirrors ProjectsTableDefinition::summarizePipelineStatus).
     * `description` rides along so the badge can carry the status'
     * explanation as its tooltip.
     *
     * @return array{id: int, name: string, color: ?string, description: ?string}|null
     */
    private function summarizeWithColor(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
            'color' => $related->color,
            'description' => $related->description,
        ];
    }

    /**
     * Display value for the AGGREGATED to-many `product_categories` column:
     * the distinct related names, comma-joined — null when there is none
     * (mirrors OpportunitiesTableDefinition::summarizeNames).
     *
     * @param  Collection<int, Model|null>  $related
     */
    private function summarizeNames(Collection $related): ?string
    {
        $names = $related->filter()->pluck('name')->unique()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }
}
