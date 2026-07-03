<?php

namespace App\Http\Resources;

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessFunction
 */
class BusinessFunctionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_business_unit' => $this->is_business_unit,
            'is_business_service' => $this->is_business_service,
            'type' => $this->type(),
            'manager_id' => $this->manager_id,
            'manager' => $this->userSummary($this->manager),
            'user_ids' => $this->users->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            'users' => $this->users->map(fn (User $user): array => $this->userSummary($user))->all(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The client-facing mutually-exclusive selector, derived from the two
     * boolean columns (spec 0010) — the inverse of BusinessFunctionService's
     * type-to-booleans mapping.
     */
    private function type(): ?string
    {
        return match (true) {
            $this->is_business_unit => 'business_unit',
            $this->is_business_service => 'business_service',
            default => null,
        };
    }

    /**
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }
}
