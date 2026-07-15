<?php

namespace App\Imports\Support;

/**
 * Outcome of GeoResolver::resolve()/resolveFuzzy(): either the resolved ids
 * (any of which may be null when its name was blank/not provided) or a
 * single motivated failure reason (name not found, or ambiguous within its
 * parent scope).
 *
 * `resolveFuzzy()` (spec 0033 AC-005) additionally distinguishes a hard
 * `failed()` (kept for `resolve()`'s exact, legacy behavior) from an
 * `ambiguous()` outcome: still unresolved (`isResolved()` false, `error`
 * set), but carrying whatever ids DID resolve unambiguously above the
 * ambiguous level, plus the `$candidates` a reviewer can pick from — the
 * caller (GeoRecognizer) turns this into a row WARNING, never a blocking
 * error.
 *
 * @phpstan-type GeoCandidate array{id: int, name: string}
 */
final readonly class GeoResolutionResult
{
    private function __construct(
        public ?int $countryId,
        public ?int $stateId,
        public ?int $provinceId,
        public ?int $cityId,
        public ?string $error,
        public bool $ambiguous = false,
        public array $candidates = [],
    ) {}

    public static function ok(?int $countryId, ?int $stateId, ?int $provinceId, ?int $cityId): self
    {
        return new self($countryId, $stateId, $provinceId, $cityId, null);
    }

    public static function failed(string $reason): self
    {
        return new self(null, null, null, null, $reason);
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $candidates
     */
    public static function ambiguous(
        ?int $countryId,
        ?int $stateId,
        ?int $provinceId,
        ?int $cityId,
        string $reason,
        array $candidates,
    ): self {
        return new self($countryId, $stateId, $provinceId, $cityId, $reason, true, $candidates);
    }

    public function isResolved(): bool
    {
        return $this->error === null;
    }
}
