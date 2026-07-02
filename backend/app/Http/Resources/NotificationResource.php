<?php

namespace App\Http\Resources;

use App\DataObjects\Notifications\NotificationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            // Normalized through the NotificationData value object so the client
            // always gets the same guaranteed shape (the four keys are always
            // present, `level` is always a valid enum value), regardless of what
            // was stored — see App\DataObjects\Notifications\NotificationData.
            'data' => NotificationData::fromArray($this->data ?? [])->toArray(),
            // Explicit ISO-8601 strings so the frontend contract is guaranteed
            // regardless of the model's serialization config (null while unread).
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
