<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Tag;
use App\Models\User;
use App\Services\Table\TableQueryBuilder;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0021 — T6: CustomFieldAwareTableDefinition decorator.
// AC-014 (columns), AC-015 (rows perf: one join, no N+1), AC-016
// (filter/sort/values, injection-safe, allow-list), AC-017 (export reuse).
uses(RefreshDatabase::class);

if (! function_exists('companiesActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function companiesActor(array $abilities = ['viewAny']): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('putCompanyCustomValues')) {
    function putCompanyCustomValues(Company $company, array $values): void
    {
        CustomFieldValue::factory()->forEntity('companies', $company->id)->create(['values' => $values]);
    }
}

// ---------------------------------------------------------------------------
// AC-014 — GET /tables/companies/columns
// ---------------------------------------------------------------------------

it('AC-014: exposes custom columns with the correct flags and enters the allow-lists', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes', 'label' => 'Notes']);
    $enum = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create(['key' => 'segment', 'label' => 'Segment']);
    $enum->options()->create(['value' => 'retail', 'label' => 'Retail', 'sort_order' => 0]);
    $enum->options()->create(['value' => 'wholesale', 'label' => 'Wholesale', 'sort_order' => 1]);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('relation')->create([
        'key' => 'owner_tag',
        'label' => 'Owner tag',
        'relation_target' => ['entity_type' => 'tags', 'cardinality' => 'one', 'for_select_resource' => 'tags'],
    ]);

    Sanctum::actingAs(companiesActor());

    $data = $this->getJson('/api/tables/companies/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($columns->has('custom.notes'))->toBeTrue()
        ->and($columns['custom.notes']['visible'])->toBeFalse()
        ->and($columns['custom.notes']['type'])->toBe('text')
        ->and($columns['custom.notes']['filterType'])->toBe('text')
        ->and($columns['custom.notes']['sortable'])->toBeTrue()
        ->and($columns['custom.notes']['filterable'])->toBeTrue()
        ->and($columns['custom.notes']['source'])->toBe('custom');

    expect($columns['custom.segment']['type'])->toBe('enum')
        ->and($columns['custom.segment']['filterType'])->toBe('set')
        ->and($columns['custom.segment']['options'])->toBe(['retail', 'wholesale'])
        ->and($columns['custom.segment']['badges'])->toBe([
            ['value' => 'retail', 'label' => 'Retail', 'color' => null, 'icon' => null],
            ['value' => 'wholesale', 'label' => 'Wholesale', 'color' => null, 'icon' => null],
        ]);

    expect($columns['custom.owner_tag']['type'])->toBe('text')
        ->and($columns['custom.owner_tag']['filterType'])->toBe('set');

    // Native columns are byte-identical to the undecorated definition.
    expect($columns['denomination']['type'])->toBe('text')
        ->and($columns->has('city'))->toBeTrue();

    // Entered the allow-lists: a sort/filter/search on a custom column is
    // accepted (never 422) by TableRowsRequest's whitelist validation.
    $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'custom.notes', 'sort' => 'asc']],
        'filterModel' => ['custom.segment' => ['filterType' => 'set', 'values' => ['retail']]],
    ])->assertOk();
});

it('a resource with no active custom fields keeps the native column list unchanged', function (): void {
    Sanctum::actingAs(companiesActor());

    $ids = collect($this->getJson('/api/tables/companies/columns')->json('data.columns'))->pluck('id');

    expect($ids->contains(fn (string $id): bool => str_starts_with($id, 'custom.')))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-015 — rows perf: one join, values in the payload, no N+1
// ---------------------------------------------------------------------------

it('AC-015: rows include custom values (text/enum/relation label) with a single join, no N+1', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    $enum = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create(['key' => 'segment']);
    $enum->options()->create(['value' => 'retail', 'label' => 'Retail']);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('relation')->create([
        'key' => 'owner_tag',
        'relation_target' => ['entity_type' => 'tags', 'cardinality' => 'one', 'for_select_resource' => 'tags'],
    ]);

    $tag = Tag::factory()->create(['name' => 'VIP']);

    foreach (range(1, 5) as $i) {
        $company = Company::factory()->create(['denomination' => "Co{$i}"]);
        putCompanyCustomValues($company, ['notes' => "note-{$i}", 'segment' => 'retail', 'owner_tag' => $tag->id]);
    }

    Sanctum::actingAs(companiesActor());

    DB::enableQueryLog();
    $response = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'custom.notes' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'note'],
            'custom.segment' => ['filterType' => 'set', 'values' => ['retail']],
            'custom.owner_tag' => ['filterType' => 'set', 'values' => [$tag->id]],
        ],
    ])->assertOk()->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $row = collect($response->json('items'))->firstWhere('denomination', 'Co1');
    expect($row['custom.notes'])->toBe('note-1')
        ->and($row['custom.segment'])->toBe('retail')
        ->and($row['custom.owner_tag'])->toBe('VIP');

    // A fixed, small number of queries regardless of row count: the values
    // join lives once in baseQuery(), and the relation label resolver
    // batches ids via whereIn (never per-row) — the ONE-TIME target-model
    // schema lookup (picking the display column) and the ONE whereIn both
    // stay constant whether there are 5 or 500 rows all sharing the same tag.
    expect($queryCount)->toBeLessThan(15);
});

it('AC-015: filtering on THREE custom columns at once still emits exactly one join to custom_field_values', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'score']);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('boolean')->create(['key' => 'active']);

    Company::factory()->create();

    $definition = app(TableRegistry::class)->resolve('companies');
    $query = app(TableQueryBuilder::class)->build($definition, [
        'filterModel' => [
            'custom.notes' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x'],
            'custom.score' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 1],
            'custom.active' => ['filterType' => 'boolean', 'values' => [true]],
        ],
    ]);

    $sql = strtolower($query->toSql());

    expect(substr_count($sql, 'left join'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-016 — filter/sort/values, injection-safe, allow-list
// ---------------------------------------------------------------------------

it('AC-016: filters custom.<key> by text/number/boolean/set via bound params', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'score']);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('boolean')->create(['key' => 'active']);
    $enum = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create(['key' => 'segment']);
    $enum->options()->create(['value' => 'retail', 'label' => 'Retail']);
    $enum->options()->create(['value' => 'wholesale', 'label' => 'Wholesale']);

    $match = Company::factory()->create(['denomination' => 'Match Co']);
    putCompanyCustomValues($match, ['notes' => "it's fine", 'score' => 42, 'active' => true, 'segment' => 'retail']);
    $other = Company::factory()->create(['denomination' => 'Other Co']);
    putCompanyCustomValues($other, ['notes' => 'nope', 'score' => 1, 'active' => false, 'segment' => 'wholesale']);

    Sanctum::actingAs(companiesActor());

    $byText = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.notes' => ['filterType' => 'text', 'type' => 'equals', 'filter' => "it's fine"]],
    ])->assertOk()->json('items.*.denomination');
    expect($byText)->toBe(['Match Co']);

    $byNumber = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.score' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 10]],
    ])->assertOk()->json('items.*.denomination');
    expect($byNumber)->toBe(['Match Co']);

    $byBoolean = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.active' => ['filterType' => 'boolean', 'values' => [true]]],
    ])->assertOk()->json('items.*.denomination');
    expect($byBoolean)->toBe(['Match Co']);

    $bySet = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.segment' => ['filterType' => 'set', 'values' => ['wholesale']]],
    ])->assertOk()->json('items.*.denomination');
    expect($bySet)->toBe(['Other Co']);
});

it('regression: a column-visibility preference for a custom.<key> column persists (not rejected)', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);

    Sanctum::actingAs(companiesActor());

    // Was 422 (custom.notes absent from the allow-list) → the whole save
    // rejected → the column reverted to hidden on reload.
    $this->postJson('/api/tables/companies/preferences', [
        'columns' => [['id' => 'custom.notes', 'visible' => true, 'order' => 99]],
    ])->assertOk();

    $byId = collect($this->getJson('/api/tables/companies/columns')->json('data.columns'))->keyBy('id');
    expect($byId)->toHaveKey('custom.notes')
        ->and($byId['custom.notes']['visible'])->toBeTrue();
});

it('AC-016 regression: filters custom.<key> wrapped as agMultiColumnFilter multi/combined', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'score']);

    $match = Company::factory()->create(['denomination' => 'Match Co']);
    putCompanyCustomValues($match, ['notes' => 'alpha', 'score' => 42]);
    $other = Company::factory()->create(['denomination' => 'Other Co']);
    putCompanyCustomValues($other, ['notes' => 'beta', 'score' => 1]);

    Sanctum::actingAs(companiesActor());

    // text column: agMultiColumnFilter wraps the typed condition in `multi`.
    $byMultiText = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.notes' => [
            'filterType' => 'multi',
            'filterModels' => [null, ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alph']],
        ]],
    ])->assertOk()->json('items.*.denomination');
    expect($byMultiText)->toBe(['Match Co']);

    // number column: the multi's Set sub-model narrows to a scalar value list.
    $byMultiSet = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.score' => [
            'filterType' => 'multi',
            'filterModels' => [['filterType' => 'set', 'values' => [42]], null],
        ]],
    ])->assertOk()->json('items.*.denomination');
    expect($byMultiSet)->toBe(['Match Co']);

    // combined OR conditions inside the typed sub-model.
    $byCombined = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.notes' => [
            'filterType' => 'multi',
            'filterModels' => [null, [
                'filterType' => 'text',
                'operator' => 'OR',
                'conditions' => [
                    ['filterType' => 'text', 'type' => 'equals', 'filter' => 'alpha'],
                    ['filterType' => 'text', 'type' => 'equals', 'filter' => 'gamma'],
                ],
            ]],
        ]],
    ])->assertOk()->json('items.*.denomination');
    expect($byCombined)->toBe(['Match Co']);
});

it('AC-016: sorts by custom.<key> correctly', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create(['key' => 'score']);

    $low = Company::factory()->create(['denomination' => 'Low']);
    putCompanyCustomValues($low, ['score' => 1]);
    $high = Company::factory()->create(['denomination' => 'High']);
    putCompanyCustomValues($high, ['score' => 99]);

    Sanctum::actingAs(companiesActor());

    $names = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'custom.score', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.denomination');

    expect($names)->toBe(['Low', 'High']);
});

it('AC-016: /values returns the distinct values for a custom.<key> column', function (): void {
    $enum = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create(['key' => 'segment']);
    $enum->options()->create(['value' => 'retail', 'label' => 'Retail']);
    $enum->options()->create(['value' => 'wholesale', 'label' => 'Wholesale']);

    putCompanyCustomValues(Company::factory()->create(), ['segment' => 'retail']);
    putCompanyCustomValues(Company::factory()->create(), ['segment' => 'wholesale']);
    putCompanyCustomValues(Company::factory()->create(), ['segment' => 'retail']);

    Sanctum::actingAs(companiesActor());

    $values = $this->postJson('/api/tables/companies/values', ['columnId' => 'custom.segment'])
        ->assertOk()
        ->json('data.values');

    expect($values)->toBe(['retail', 'wholesale']);
});

it('AC-016: a columnId outside the allow-list is rejected with 422 (never reaches the query)', function (): void {
    Sanctum::actingAs(companiesActor());

    $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'custom.ghost', 'sort' => 'asc']],
    ])->assertStatus(422);

    $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.ghost' => ['filterType' => 'text', 'type' => 'equals', 'filter' => 'x']],
    ])->assertStatus(422);

    $this->postJson('/api/tables/companies/values', ['columnId' => 'custom.ghost'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('columnId');
});

// ---------------------------------------------------------------------------
// AC-017 — export reuses TableQueryBuilder + mapRow, no dedicated code
// ---------------------------------------------------------------------------

it('AC-017: TableQueryBuilder::build() + mapRow() resolve a selected custom column, exactly as export would', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    $company = Company::factory()->create(['denomination' => 'Export Co']);
    putCompanyCustomValues($company, ['notes' => 'exported value']);

    $definition = app(TableRegistry::class)->resolve('companies');
    $state = [
        'columns' => [
            ['colId' => 'denomination', 'header' => 'Denomination'],
            ['colId' => 'custom.notes', 'header' => 'Notes'],
        ],
    ];

    $query = app(TableQueryBuilder::class)->build($definition, $state);
    $model = $query->first();

    $actor = User::factory()->create();
    $mapped = $definition->mapRow($actor, $model);

    expect($mapped['denomination'])->toBe('Export Co')
        ->and($mapped['custom.notes'])->toBe('exported value');
});
