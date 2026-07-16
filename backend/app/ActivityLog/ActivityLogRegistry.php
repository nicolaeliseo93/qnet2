<?php

namespace App\ActivityLog;

use App\DataObjects\ActivityLog\ActivityLogDefinition;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{resource}` string (GET /api/activity-log/{resource}/{id}) → its
 * ActivityLogDefinition, mirroring TableRegistry (App\Tables\TableRegistry):
 * an explicit config map (config/activity-log.php), unknown resource →
 * ModelNotFoundException → 404 (via BaseApiController). Adding a resource is
 * one config line, no controller/service change.
 */
final class ActivityLogRegistry
{
    /**
     * @throws ModelNotFoundException when the resource is not registered.
     */
    public function resolve(string $resource): ActivityLogDefinition
    {
        /** @var array<string, array{model: class-string, relations: array<int, string>}> $definitions */
        $definitions = config('activity-log.resources', []);

        $config = $definitions[$resource] ?? null;

        if ($config === null) {
            throw (new ModelNotFoundException)->setModel(ActivityLogDefinition::class, [$resource]);
        }

        return new ActivityLogDefinition($config['model'], $config['relations'] ?? []);
    }
}
