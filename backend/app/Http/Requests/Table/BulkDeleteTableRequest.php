<?php

namespace App\Http\Requests\Table;

use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/tables/{domain}/bulk-delete.
 *
 * Mirrors TableRowsRequest/TablePreferencesRequest: the domain is unknown at
 * boot, so it is resolved here from the {domain} route segment — an UNKNOWN
 * domain surfaces as 404 BEFORE validation runs (TableRegistry::resolve
 * throws ModelNotFoundException), never a misleading 422, consistent with
 * every other tables/{domain}/* endpoint.
 *
 * `ids` itself carries no domain-specific whitelist (plain integers): the
 * per-id authorization and domain delete guards are enforced downstream by
 * TableBulkDeleteService via the resolved TableDefinition.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via the definition's viewAny), same convention as the other
 * Table FormRequests.
 */
class BulkDeleteTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the definition's viewAny.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Resolving the domain here (before the rules below run) guarantees an
        // unknown domain always surfaces as 404, even alongside an invalid ids
        // payload — mirroring TableRowsRequest/TablePreferencesRequest.
        $domain = (string) $this->route('domain');
        app(TableRegistry::class)->resolve($domain);

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * The validated ids, as integers.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('ids', []);

        return array_map(intval(...), $ids);
    }
}
