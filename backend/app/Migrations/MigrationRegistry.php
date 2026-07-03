<?php

namespace App\Migrations;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maps a `{source}` string -> its MigrationSource. Mirrors
 * App\Tables\TableRegistry / App\Imports\ImportRegistry exactly.
 *
 * Registration is an explicit config map (config/migrations.php) resolved
 * through the container, so a source's dependencies (e.g. RoleService) are
 * injected. Adding a source = write one XxxSource + add one line to that
 * config. No new Controller/Service/Request/Resource/route.
 *
 * Unknown source -> ModelNotFoundException -> 404 (via BaseApiController).
 */
class MigrationRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * @throws ModelNotFoundException when the source is not registered.
     */
    public function resolve(string $key): MigrationSource
    {
        /** @var array<string, class-string<MigrationSource>> $definitions */
        $definitions = config('migrations.definitions', []);

        $class = $definitions[$key] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException)->setModel(MigrationSource::class, [$key]);
        }

        /** @var MigrationSource $source */
        $source = $this->container->make($class);

        return $source;
    }

    /**
     * Every registered source, resolved (for the sources index endpoint).
     *
     * @return array<int, MigrationSource>
     */
    public function all(): array
    {
        /** @var array<string, class-string<MigrationSource>> $definitions */
        $definitions = config('migrations.definitions', []);

        return array_map(
            fn (string $key): MigrationSource => $this->resolve($key),
            array_keys($definitions),
        );
    }
}
