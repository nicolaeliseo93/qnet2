<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\ContactTypeEnum;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Derived global quick-search (spec 0009) for the `request-management`
 * domain's CLIENT anagraphic columns. Those columns carry no real
 * `opportunities` column — RequestRowMapper resolves them from the Registry's
 * PersonalData card (and its primary phone contact) — so the generic engine's
 * plain `orWhere($column, 'like', ...)` would target a non-existent column.
 * This collaborator translates each of them into an `orWhereHas` over the
 * relation the mapper reads, exactly mirroring OperationalSiteGeoColumns'
 * `applySearch` precedent, and keeps RequestManagementTableDefinition within
 * its file-size budget (engineering.md §6).
 *
 * SECURITY: the column ids come from the definition's server-side allow-list
 * (never the request) and `$pattern` is the engine's already LIKE-escaped
 * bound parameter — never interpolated.
 */
final class RequestClientSearch
{
    /**
     * PersonalData card columns reachable by quick-search, keyed by the
     * derived table column id they back (identical names here, but the map
     * keeps the allow-list explicit).
     *
     * @var array<string, string>
     */
    private const array CARD_COLUMNS = [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'tax_code' => 'tax_code',
    ];

    /**
     * The card relation path from an Opportunity, as read by RequestRowMapper.
     */
    private const string CARD_RELATION = 'registry.personalData';

    /**
     * Add the OR-branch for one searchable derived column to the engine's
     * search group. Returns false for any column this collaborator does not
     * own, so the generic engine handles it.
     *
     * @param  Builder<Opportunity>  $query
     */
    public function apply(Builder $query, string $columnId, string $pattern): bool
    {
        if (isset(self::CARD_COLUMNS[$columnId])) {
            $column = self::CARD_COLUMNS[$columnId];
            $query->orWhereHas(
                self::CARD_RELATION,
                static fn (Builder $cardQuery) => $cardQuery->where($column, 'like', $pattern),
            );

            return true;
        }

        if ($columnId === 'phone') {
            $query->orWhereHas(
                self::CARD_RELATION.'.contacts',
                static fn (Builder $contactQuery) => $contactQuery
                    ->where('is_primary', true)
                    ->whereIn('type', [ContactTypeEnum::Phone->value, ContactTypeEnum::Mobile->value])
                    ->where('value', 'like', $pattern),
            );

            return true;
        }

        return false;
    }
}
