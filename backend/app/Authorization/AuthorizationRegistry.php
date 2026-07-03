<?php

declare(strict_types=1);

namespace App\Authorization;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{resource}` string → its ResourceAuthorization. Mirrors
 * App\Tables\TableRegistry: an explicit config map (config/authorization.php)
 * resolved through the container, so a definition's dependencies are
 * injected. Adding a resource = one class + one config line.
 *
 * Unknown resource → ModelNotFoundException → 404 (via BaseApiController).
 */
class AuthorizationRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the ResourceAuthorization for the given resource key.
     *
     * @throws ModelNotFoundException when the resource is not registered.
     */
    public function resolve(string $resource): ResourceAuthorization
    {
        /** @var array<string, class-string<ResourceAuthorization>> $definitions */
        $definitions = config('authorization.definitions', []);

        $class = $definitions[$resource] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(ResourceAuthorization::class, [$resource]);
        }

        /** @var ResourceAuthorization $authorization */
        $authorization = $this->container->make($class);

        return $authorization;
    }
}
