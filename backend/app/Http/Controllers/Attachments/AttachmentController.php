<?php

namespace App\Http\Controllers\Attachments;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Attachments\IndexAttachmentRequest;
use App\Http\Requests\Attachments\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Endpoints for the polymorphic file-attachment system: list, upload,
 * metadata, authenticated download/inline view and delete.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (AttachmentPolicy), Service call, response. No business logic, no queries.
 * The binary is never served statically — download/view stream through these
 * authorized endpoints only.
 *
 * @see AttachmentService
 */
class AttachmentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly AttachmentService $service) {}

    /**
     * GET /api/attachments — list the files owned by one polymorphic owner,
     * optionally narrowed to a named collection, newest first.
     */
    public function index(IndexAttachmentRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Attachment::class);

            $attachments = Attachment::query()
                ->where('attachable_type', $request->validated('attachable_type'))
                ->where('attachable_id', $request->validated('attachable_id'))
                ->when(
                    $request->filled('collection'),
                    fn ($query) => $query->where('collection', $request->validated('collection')),
                )
                ->latest()
                ->get();

            return $this->ok(AttachmentResource::collection($attachments));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/attachments — upload a file, optionally linking it to an owner.
     */
    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Attachment::class);

            $attachment = $this->service->store($request->user(), $request->toData());

            return $this->created(new AttachmentResource($attachment));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/attachments/{attachment} — file metadata.
     */
    public function show(Attachment $attachment): JsonResponse
    {
        try {
            $this->authorize('view', $attachment);

            return $this->ok(new AttachmentResource($attachment));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }

    /**
     * GET /api/attachments/{attachment}/download — stream the binary.
     *
     * Returns the raw file (not JSON) on success; falls back to the standard
     * JSON error envelope when the stored object is missing or unreadable.
     */
    public function download(Attachment $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $attachment);

            $disk = Storage::disk($attachment->disk);

            if (! $disk->exists($attachment->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->download($attachment->path, $attachment->original_name);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }

    /**
     * GET /api/attachments/{attachment}/view — stream the binary inline, so a
     * browser/iframe renders it (e.g. PDF/image preview) instead of the
     * "Save as" prompt forced by download()'s Content-Disposition: attachment.
     *
     * Returns the raw file (not JSON) on success; falls back to the standard
     * JSON error envelope when the stored object is missing or unreadable.
     */
    public function view(Attachment $attachment): StreamedResponse|JsonResponse
    {
        try {
            $this->authorize('view', $attachment);

            $disk = Storage::disk($attachment->disk);

            if (! $disk->exists($attachment->path)) {
                abort(404, 'The requested file no longer exists.');
            }

            return $disk->response(
                $attachment->path,
                $attachment->original_name,
                ['Content-Type' => $attachment->mime_type],
                'inline',
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }

    /**
     * DELETE /api/attachments/{attachment} — delete metadata and binary.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        try {
            $this->authorize('delete', $attachment);

            $this->service->delete($attachment);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attachment' => $attachment->id]);
        }
    }
}
