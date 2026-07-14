<?php

declare(strict_types=1);

namespace App\Stats;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{domain}` string → its StatsDefinition (spec 0026). Mirrors
 * App\Tables\TableRegistry / App\Authorization\AuthorizationRegistry: an
 * explicit config map (config/stats.php) resolved through the container, so a
 * definition's dependencies are injected. Adding a module's panel = one class
 * + one config line.
 *
 * Unknown domain → ModelNotFoundException → 404 (via BaseApiController). The
 * {domain} segment is user-controlled: only config-mapped domains resolve, so
 * no arbitrary class can ever be reflected.
 */
class StatsRegistry
{
    /**
     * Subject of the not-found exception. A neutral token, NOT a class name:
     * the 404 message reaches the client (BaseApiController), so it must not
     * disclose an internal FQCN (spec 0026, AC-003).
     */
    private const string NOT_FOUND_SUBJECT = 'stats';

    public function __construct(private readonly Container $container) {}

    /**
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolve(string $domain): StatsDefinition
    {
        /** @var array<string, class-string<StatsDefinition>> $definitions */
        $definitions = config('stats.definitions', []);

        $class = $definitions[$domain] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(self::NOT_FOUND_SUBJECT, [$domain]);
        }

        /** @var StatsDefinition $definition */
        $definition = $this->container->make($class);

        return $definition;
    }
}
