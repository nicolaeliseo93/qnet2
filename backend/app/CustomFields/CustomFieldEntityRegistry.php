<?php

declare(strict_types=1);

namespace App\CustomFields;

use App\Authorization\AuthorizationRegistry;
use App\Tables\TableRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves which domains are "custom-fieldable" (spec 0021) and maps a
 * `entity_type` string to its {model_class, resource, domain}.
 *
 * An entity is custom-fieldable ONLY IF its key is registered in BOTH
 * `config/tables.php` (TableRegistry) AND `config/authorization.php`
 * (AuthorizationRegistry) — the two seams the decorators (T5/T6) hook into.
 * `entity_type` IS the domain/resource key (same string convention across
 * the framework, e.g. "companies").
 *
 * The map is built once and memoized in-process; bind this class as a
 * container singleton (see AppServiceProvider) so the build cost — resolving
 * every custom-fieldable TableDefinition through the container — is paid at
 * most once per request, not once per call site.
 */
class CustomFieldEntityRegistry
{
    /**
     * @var array<string, array{model_class: class-string<Model>, resource: string, domain: string}>|null
     */
    private ?array $map = null;

    public function __construct(
        private readonly TableRegistry $tableRegistry,
        private readonly AuthorizationRegistry $authorizationRegistry,
    ) {}

    /**
     * Custom-fieldable domains for the admin picker (`GET
     * /custom-fields/entities`). `label` is an i18n key
     * (`customFields.entities.{entity_type}`) — the frontend owns the actual
     * translated string, keeping this registry free of user-facing copy.
     *
     * @return array<int, array{entity_type: string, label: string}>
     */
    public function entities(): array
    {
        return array_map(
            static fn (string $entityType): array => [
                'entity_type' => $entityType,
                'label' => "customFields.entities.{$entityType}",
            ],
            array_keys($this->map())
        );
    }

    public function isCustomFieldable(string $entityType): bool
    {
        return array_key_exists($entityType, $this->map());
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelClassFor(string $entityType): ?string
    {
        return $this->map()[$entityType]['model_class'] ?? null;
    }

    public function resourceFor(string $entityType): ?string
    {
        return $this->map()[$entityType]['resource'] ?? null;
    }

    /**
     * Reverse lookup: the entity_type owning the given model instance, or
     * null when the model's class is not registered as custom-fieldable.
     */
    public function entityTypeForModel(Model $model): ?string
    {
        $modelClass = $model::class;

        foreach ($this->map() as $entityType => $entry) {
            if ($entry['model_class'] === $modelClass) {
                return $entityType;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{model_class: class-string<Model>, resource: string, domain: string}>
     */
    private function map(): array
    {
        return $this->map ??= $this->build();
    }

    /**
     * @return array<string, array{model_class: class-string<Model>, resource: string, domain: string}>
     */
    private function build(): array
    {
        $tableDomains = array_keys(config('tables.definitions', []));
        $authorizedResources = $this->authorizationRegistry->resourceKeys();

        $customFieldableDomains = array_intersect($tableDomains, $authorizedResources);

        $map = [];

        foreach ($customFieldableDomains as $domain) {
            // resolveRaw(), not resolve(): this map is what TableRegistry::resolve()
            // itself consults to decide whether to wrap a domain, so calling the
            // decorated resolve() here would re-enter isCustomFieldable() before
            // this very build() call returns — infinite recursion. modelClass()/
            // resource() are identical whether the definition is wrapped or not.
            $definition = $this->tableRegistry->resolveRaw($domain);

            $map[$domain] = [
                'model_class' => $definition->modelClass(),
                'resource' => $definition->resource(),
                'domain' => $domain,
            ];
        }

        return $map;
    }
}
