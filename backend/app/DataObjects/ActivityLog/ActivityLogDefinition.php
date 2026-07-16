<?php

namespace App\DataObjects\ActivityLog;

use Illuminate\Database\Eloquent\Model;

/**
 * One `config/activity-log.php` entry, resolved (spec 0034): the root model
 * class of a `{resource}` and the dot-path relations aggregated alongside it.
 */
final readonly class ActivityLogDefinition
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $relations
     */
    public function __construct(
        public string $model,
        public array $relations,
    ) {}
}
