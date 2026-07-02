<?php

namespace App\Http\Requests\Notifications;

use App\DataObjects\Notifications\NotificationListData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/notifications.
 *
 * Authorization is intentionally NOT handled here: these endpoints are
 * ownership-scoped by construction (always auth()->user()->notifications()), so
 * there is no per-request permission to check — see ADR-0005.
 */
class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership-scoped via the relationship in NotificationService; nothing
        // to authorize here.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', Rule::in(['all', 'unread'])],
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): NotificationListData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return NotificationListData::fromValidated($validated);
    }
}
