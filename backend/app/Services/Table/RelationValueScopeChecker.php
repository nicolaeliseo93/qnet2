<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\DataObjects\Shared\ForSelectQuery;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\OperationalSiteService;
use App\Services\ProductService;
use App\Services\RegistryService;
use App\Services\SourceService;
use App\Services\UserService;

/**
 * D-2 of spec 0054: a relation column's submitted id must be MORE than
 * `exists:<table>,id` — it must be among the rows the actor could actually
 * pick from that resource's own `/for-select` endpoint, otherwise inline-edit
 * becomes a channel to link an otherwise-invisible record. Two guards mirror
 * exactly what the real `/for-select` HTTP endpoint enforces:
 *  - the actor must hold `{resource}.viewAny` (every `*ForSelectController`
 *    calls `$this->authorize('viewAny', ...)` before ever running its query —
 *    an actor without it could never have reached the query at all);
 *  - the value must resolve through the SAME query each
 *    `<Resource>Service::forSelect()` already runs (never a hand-rolled scope
 *    query here): `limit: 0` skips the default page entirely and
 *    `ids: [$value]` forces the service's own edit-mode hydration path to
 *    resolve just that one id through its real base query.
 *
 * Bounded to the relation resources spec 0054 activates (leads' 5 columns).
 * Extend the match arm when a future column activates a new relation
 * resource — an unmapped resource fails closed (never editable), consistent
 * with every other fail-safe in this engine.
 */
final class RelationValueScopeChecker
{
    public function __construct(
        private readonly RegistryService $registries,
        private readonly CampaignService $campaigns,
        private readonly OperationalSiteService $operationalSites,
        private readonly SourceService $sources,
        private readonly UserService $users,
        private readonly ProductService $products,
    ) {}

    /**
     * Whether $value is a real id $actor could select from $resource's own
     * `/for-select` query — a nonexistent id, an out-of-scope id and an
     * actor lacking `{resource}.viewAny` are all indistinguishable here by
     * design (D-2: all three are 422, none confirms which).
     */
    public function inScope(string $resource, int $value, User $actor): bool
    {
        if (! $actor->can("{$resource}.viewAny")) {
            return false;
        }

        $query = new ForSelectQuery(search: null, offset: 0, limit: 0, ids: [$value]);

        $items = match ($resource) {
            'registries' => $this->registries->forSelect($query)->items,
            'campaigns' => $this->campaigns->forSelect($query)->items,
            'operational-sites' => $this->operationalSites->forSelect($query)->items,
            'sources' => $this->sources->forSelect($query)->items,
            'users' => $this->users->forSelect($query)->items,
            // User directive 2026-07-23: the `products_of_interest` MULTISELECT
            // column checks every submitted id through this same gate, one id
            // at a time (CellValueValidator::validateIdListValue).
            'products' => $this->products->forSelect($query)->items,
            default => null,
        };

        if ($items === null) {
            return false;
        }

        return $items->contains(static fn (mixed $item): bool => (int) $item->getKey() === $value);
    }
}
