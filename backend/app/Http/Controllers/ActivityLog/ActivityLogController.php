<?php

namespace App\Http\Controllers\ActivityLog;

use App\ActivityLog\ActivityLogRegistry;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ActivityLog\ActivityLogIndexRequest;
use App\Http\Resources\ActivityLog\ActivityLogEntryResource;
use App\Services\ActivityLog\AggregatedActivityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Generic Activity Log endpoint (spec 0034): one route serves every resource
 * registered in config/activity-log.php (v1: `users`). Thin controller: the
 * registry resolves {resource} → model/relations (unknown → 404, via
 * ActivityLogRegistry), the record itself is fetched with those relations
 * eager-loaded (missing → 404), then authorization is `{resource}.viewActivity`
 * AND the model's own Policy `view` — no log of what the actor cannot see.
 *
 * @see AggregatedActivityService
 */
class ActivityLogController extends BaseApiController
{
    use AuthorizesRequests;

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

            $this->authorize('viewActivity', $modelClass);
            $this->authorize('view', $record);

            $page = $this->service->paginate($record, $definition->relations, $request->perPage(), $request->cursor());

            return $this->ok([
                'items' => ActivityLogEntryResource::collection($page->items),
                'next_cursor' => $page->nextCursor,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['resource' => $resource, 'id' => $id]);
        }
    }
}
