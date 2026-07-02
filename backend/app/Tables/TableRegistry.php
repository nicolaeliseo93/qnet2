<?php

namespace App\Tables;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{domain}` string → its TableDefinition.
 *
 * Registration is an explicit config map (config/tables.php) resolved through
 * the container, so a definition's dependencies (e.g. UserService) are injected.
 * Adding a domain = write one XxxTableDefinition + add one line to that config.
 * No new Controller/Service/Request/Resource/route.
 *
 * Unknown domain → ModelNotFoundException → 404 (via BaseApiController). The
 * {domain} segment is user-controlled: only config-mapped domains resolve;
 * everything else is unreachable.
 */
class TableRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the definition for the given domain.
     *
     * @throws ModelNotFoundException when the domain is not registered.
     */
    public function resolve(string $domain): TableDefinition
    {
        /** @var array<string, class-string<TableDefinition>> $definitions */
        $definitions = config('tables.definitions', []);

        $class = $definitions[$domain] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(TableDefinition::class, [$domain]);
        }

        /** @var TableDefinition $definition */
        $definition = $this->container->make($class);

        return $definition;
    }
}
