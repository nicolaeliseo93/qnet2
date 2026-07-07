<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backend-driven navigation (Level 0)
    |--------------------------------------------------------------------------
    |
    | Declarative menu tree. NavigationService filters these items by the
    | current user's permissions (Spatie). The frontend just renders what it
    | receives — it never decides visibility.
    |
    | Item shape:
    |   - key:        stable identifier (string)
    |   - label:      i18n key resolved by the frontend (e.g. "navigation.users")
    |   - icon:       icon name the frontend maps to a component (nullable)
    |   - route:      frontend route (nullable for pure groups)
    |   - permission: required permission, or null = any authenticated user
    |   - role:       (optional, spec 0013) required Spatie role name — item is
    |                 visible ONLY to a user holding it (in ADDITION to the
    |                 permission check above, if any). Omit for ordinary items;
    |                 never used by SyncPermissions (it has nothing to do with
    |                 the permission catalogue).
    |   - type:       'item' (default) or 'section'. A 'section' is a labeled
    |                 separator: the frontend renders its children as flat,
    |                 sibling links under a group label — NOT as a collapsible
    |                 parent. Omit for ordinary items.
    |   - children:   nested items (optional)
    |
    | A group/section (route = null) with no visible children is removed
    | automatically by NavigationService.
    |
    | NOTE: hiding a menu item is UX only. Every endpoint must still enforce
    | authorization server-side via Policies/permissions.
    |
    */

    'items' => [
        [
            'key' => 'dashboard',
            'label' => 'navigation.dashboard',
            'icon' => 'layout-dashboard',
            'route' => '/dashboard',
            'permission' => null,
        ],
        [
            'key' => 'settings',
            'label' => 'navigation.settings',
            'icon' => null,
            'route' => null,
            'permission' => null,
            'type' => 'section',
            'children' => [
                [
                    'key' => 'users',
                    'label' => 'navigation.users',
                    'icon' => 'users',
                    'route' => '/users',
                    'permission' => 'users.view',
                ],
                [
                    'key' => 'roles',
                    'label' => 'navigation.roles',
                    'icon' => 'shield-check',
                    'route' => '/roles',
                    'permission' => 'roles.view',
                ],
                [
                    'key' => 'fa-companies-services',
                    'label' => 'navigation.faCompaniesServices',
                    'icon' => 'layers',
                    'route' => null,
                    'permission' => null,
                    'children' => [
                        [
                            'key' => 'business-functions',
                            'label' => 'navigation.businessFunctions',
                            'icon' => 'briefcase',
                            'route' => '/business-functions',
                            'permission' => 'business-functions.view',
                        ],
                        [
                            'key' => 'companies',
                            'label' => 'navigation.companies',
                            'icon' => 'building',
                            'route' => '/companies',
                            'permission' => 'companies.view',
                        ],
                        [
                            // Company Sites (spec 0020): flexible site
                            // anagraphic under a Company.
                            'key' => 'company-sites',
                            'label' => 'navigation.companySites',
                            'icon' => 'building-2',
                            'route' => '/company-sites',
                            'permission' => 'company-sites.view',
                        ],
                        [
                            'key' => 'operational-sites',
                            'label' => 'navigation.operationalSites',
                            'icon' => 'map-pin',
                            'route' => '/operational-sites',
                            'permission' => 'operational-sites.view',
                        ],
                    ],
                ],
                [
                    // Referents (spec 0016): a contact person/entity reusing the
                    // users anagraphic stack. Modelled as a collapsible group
                    // (same shape as `fa-companies-services`) so its full-CRUD
                    // lookup `referent-types` nests UNDER referents instead of
                    // sitting as a flat sibling. The route-less group is dropped
                    // automatically when the actor can see none of its children.
                    'key' => 'referents-group',
                    'label' => 'navigation.referents',
                    'icon' => 'contact-round',
                    'route' => null,
                    'permission' => null,
                    'children' => [
                        [
                            'key' => 'referents',
                            'label' => 'navigation.referents',
                            'icon' => 'contact-round',
                            'route' => '/referents',
                            'permission' => 'referents.view',
                        ],
                        [
                            'key' => 'referent-types',
                            'label' => 'navigation.referentTypes',
                            'icon' => 'tags',
                            'route' => '/referent-types',
                            'permission' => 'referent-types.view',
                        ],
                        [
                            // EA sectors (spec 0018): a standalone lookup used
                            // to classify Anagrafiche in the future (no such
                            // relation exists yet — see spec 0018 scope).
                            'key' => 'ea-sectors',
                            'label' => 'navigation.eaSectors',
                            'icon' => 'list-tree',
                            'route' => '/ea-sectors',
                            'permission' => 'ea-sectors.view',
                        ],
                        [
                            // Tags (spec 0019): a reusable, polymorphic
                            // lookup attached to any entity via the
                            // `taggables` pivot. EaSector is its first
                            // producer, so it nests here alongside
                            // referent-types/ea-sectors. Uses a different
                            // icon than referent-types ('tags') to stay
                            // visually distinct.
                            'key' => 'tags',
                            'label' => 'navigation.tags',
                            'icon' => 'tag',
                            'route' => '/tags',
                            'permission' => 'tags.view',
                        ],
                    ],
                ],
                [
                    // Sources (spec 0018): a standalone lookup used to
                    // classify the provenance of registry records
                    // ("Anagrafiche"). Flat sibling entry (no group of its
                    // own children yet), placed next to the other support
                    // lookups (referent-types).
                    'key' => 'sources',
                    'label' => 'navigation.sources',
                    'icon' => 'waypoints',
                    'route' => '/sources',
                    'permission' => 'sources.view',
                ],
                [
                    // Products (spec 0017): a configurable catalogue —
                    // Attributes (reusable dynamic fields), Product
                    // Categories (the attribute-assignment tree) and Products
                    // itself. The route-less group is dropped automatically
                    // when the actor can see none of its children.
                    'key' => 'products-group',
                    'label' => 'navigation.products',
                    'icon' => 'package',
                    'route' => null,
                    'permission' => null,
                    'children' => [
                        [
                            'key' => 'products',
                            'label' => 'navigation.products',
                            'icon' => 'package',
                            'route' => '/products',
                            'permission' => 'products.view',
                        ],
                        [
                            'key' => 'product-categories',
                            'label' => 'navigation.productCategories',
                            'icon' => 'list-tree',
                            'route' => '/product-categories',
                            'permission' => 'product-categories.view',
                        ],
                        [
                            'key' => 'attributes',
                            'label' => 'navigation.attributes',
                            'icon' => 'sliders-horizontal',
                            'route' => '/attributes',
                            'permission' => 'attributes.view',
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'migrations',
            // Namespaced i18n key: `migrations` strings live in their own i18next
            // namespace (see frontend i18n/index.ts), not the default bundle.
            'label' => 'migrations:nav.label',
            'icon' => 'database-zap',
            'route' => '/migrations',
            'permission' => null,
            'role' => 'super-admin',
        ],
    ],

];
