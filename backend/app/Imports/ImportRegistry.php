<?php

namespace App\Imports;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{domain}` string → its ImportDefinition. Mirrors
 * App\Tables\TableRegistry exactly.
 *
 * Registration is an explicit config map (config/imports.php) resolved
 * through the container, so a definition's dependencies (e.g. a domain
 * Service, GeoResolver) are injected. Adding a domain = write one
 * XxxImportDefinition + add one line to that config. No new
 * Controller/Service/Request/Resource/route.
 *
 * Unknown domain → ModelNotFoundException → 404 (via BaseApiController). The
 * {domain} segment is user-controlled: only config-mapped domains resolve;
 * everything else is unreachable.
 */
class ImportRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolve(string $domain): ImportDefinition
    {
        /** @var array<string, class-string<ImportDefinition>> $definitions */
        $definitions = config('imports.definitions', []);

        $class = $definitions[$domain] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(ImportDefinition::class, [$domain]);
        }

        /** @var ImportDefinition $definition */
        $definition = $this->container->make($class);

        return $definition;
    }
}
