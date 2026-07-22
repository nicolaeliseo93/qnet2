<?php
use App\Models\Campaign;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('debug two-iteration parity with cache forget', function () {
    $cases = [
        'opportunities' => [
            'model' => Opportunity::factory()->create(),
            'endpoint' => fn (Opportunity $model): string => "/api/opportunities/{$model->id}",
            'payload' => ['name' => 'Parity check'],
        ],
        'campaigns' => [
            'model' => Campaign::factory()->create(),
            'endpoint' => fn (Campaign $model): string => "/api/campaigns/{$model->id}",
            'payload' => ['name' => 'Parity check'],
        ],
    ];

    foreach ($cases as $resource => $case) {
        Permission::findOrCreate("{$resource}.viewAny");
        Permission::findOrCreate("{$resource}.update");
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $actor = User::factory()->create();
        $actor->givePermissionTo(["{$resource}.viewAny", "{$resource}.update"]);
        Sanctum::actingAs($actor);

        $response = $this->patchJson(($case['endpoint'])($case['model']), $case['payload']);
        expect($response->status())->toBe(200);
    }
});
