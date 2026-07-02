<?php

namespace App\DataObjects\Auth;

use App\Models\User;

/**
 * Result of a successful authentication: the authenticated user plus the freshly
 * issued plain-text access token.
 *
 * Declared DTO (no "magic flying array") so the AuthService → AuthController
 * contract is explicit and type-safe — see standards/architecture.md →
 * Data Transfer Objects.
 */
final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
