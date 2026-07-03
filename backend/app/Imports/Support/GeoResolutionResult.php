<?php

namespace App\Imports\Support;

/**
 * Outcome of GeoResolver::resolve(): either the resolved ids (any of which may
 * be null when its name was blank/not provided) or a single motivated failure
 * reason (name not found, or ambiguous within its parent scope).
 */
final readonly class GeoResolutionResult
{
    private function __construct(
        public ?int $countryId,
        public ?int $stateId,
        public ?int $provinceId,
        public ?int $cityId,
        public ?string $error,
    ) {}

    public static function ok(?int $countryId, ?int $stateId, ?int $provinceId, ?int $cityId): self
    {
        return new self($countryId, $stateId, $provinceId, $cityId, null);
    }

    public static function failed(string $reason): self
    {
        return new self(null, null, null, null, $reason);
    }

    public function isResolved(): bool
    {
        return $this->error === null;
    }
}
