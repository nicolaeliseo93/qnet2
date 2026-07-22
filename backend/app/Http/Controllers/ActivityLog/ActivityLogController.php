<?php

namespace App\Http\Controllers\ActivityLog;

use App\ActivityLog\ActivityLogRegistry;
use App\ActivityLog\Contracts\ActivityLogAuthorizer;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ActivityLog\ActivityLogIndexRequest;
use App\Http\Resources\ActivityLog\ActivityLogEntryResource;
use App\Models\User;
use App\Services\ActivityLog\AggregatedActivityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Generic Activity Log endpoint (spec 0034): one route serves every resource
 * registered in config/activity-log.php (v1: `users`). Thin controller: the
 * registry resolves {resource} → model/relations/authorizer (unknown → 404,
 * via ActivityLogRegistry), the record itself is fetched with those relations
 * eager-loaded (missing → 404), then the resource's own ActivityLogAuthorizer
 * decides — by default the model's Policy (`{resource}.viewActivity` AND
 * `view`), so no actor ever reads the log of what they cannot see.
 *
 * @see AggregatedActivityService
 */
class ActivityLogController extends BaseApiController
{
    public function __construct(
        private readonly ActivityLogRegistry $registry,
        private readonly AggregatedActivityService $service,
    ) {}

    /**
     * GET /api/activity-log/{resource}/{id}.
     */
    public function index(ActivityLogIndexRequest $request, string $resource, int $id): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($resource);

            /** @var class-string<Model> $modelClass */
            $modelClass = $definition->model;

            /** @var Model $record */
            $record = $modelClass::query()->with($definition->relations)->findOrFail($id);

            /** @var User $actor */
            $actor = $request->user();

            /** @var ActivityLogAuthorizer $authorizer */
            $authorizer = app($definition->authorizer);
            $authorizer->authorize($actor, $record);

            $page = $this->service->paginate($record, $definition->relations, $request->perPage(), $request->cursor());

            return $this->ok([
                'items' => $page->items->map(
                    fn (Activity $activity): ActivityLogEntryResource => new ActivityLogEntryResource($activity, $page->labels)
                ),
                'next_cursor' => $page->nextCursor,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['resource' => $resource, 'id' => $id]);
        }
    }
}
