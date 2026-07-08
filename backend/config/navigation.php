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

        // "Gestione": the day-to-day operational records (master data). Flat
        // leaves under a section label — no collapsible sub-groups. Ordered
        // from the central anagraphic entity outward.
        [
            'key' => 'management',
            'label' => 'navigation.management',
            'icon' => null,
            'route' => null,
            'permission' => null,
            'type' => 'section',
            'children' => [
                [
                    'key' => 'registries',
                    'label' => 'navigation.registries',
                    'icon' => 'book-user',
                    'route' => '/registries',
                    'permission' => 'registries.view',
                ],
                [
                    'key' => 'referents',
                    'label' => 'navigation.referents',
                    'icon' => 'contact-round',
                    'route' => '/referents',
                    'permission' => 'referents.view',
                ],
                [
                    'key' => 'companies',
                    'label' => 'navigation.companies',
                    'icon' => 'building',
                    'route' => '/companies',
                    'permission' => 'companies.view',
                ],
                [
                    'key' => 'operational-sites',
                    'label' => 'navigation.operationalSites',
                    'icon' => 'map-pin',
                    'route' => '/operational-sites',
                    'permission' => 'operational-sites.view',
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
                    'key' => 'products',
                    'label' => 'navigation.products',
                    'icon' => 'package',
                    'route' => '/products',
                    'permission' => 'products.view',
                ],
            ],
        ],

        // "Configurazione": every support/lookup table gathered in one place
        // (grant the operational modules their reference values). This is the
        // single settings area the product asked for — mirrors the Setup areas
        // of Salesforce/Zoho where pick-lists and reusable fields live apart
        // from the daily-work records.
        [
            'key' => 'configuration',
            'label' => 'navigation.configuration',
            'icon' => null,
            'route' => null,
            'permission' => null,
            'type' => 'section',
            'children' => [
                [
                    'key' => 'business-functions',
                    'label' => 'navigation.businessFunctions',
                    'icon' => 'briefcase',
                    'route' => '/business-functions',
                    'permission' => 'business-functions.view',
                ],
                [
                    'key' => 'referent-types',
                    'label' => 'navigation.referentTypes',
                    'icon' => 'tags',
                    'route' => '/referent-types',
                    'permission' => 'referent-types.view',
                ],
                [
                    'key' => 'sectors',
                    'label' => 'navigation.sectors',
                    'icon' => 'list-tree',
                    'route' => '/sectors',
                    'permission' => 'sectors.view',
                ],
                [
                    'key' => 'tags',
                    'label' => 'navigation.tags',
                    'icon' => 'tag',
                    'route' => '/tags',
                    'permission' => 'tags.view',
                ],
                [
                    'key' => 'sources',
                    'label' => 'navigation.sources',
                    'icon' => 'waypoints',
                    'route' => '/sources',
                    'permission' => 'sources.view',
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
                [
                    // Universal custom fields (spec 0021): the admin catalogue
                    // of dynamic fields grafted onto every custom-fieldable
                    // module.
                    'key' => 'custom-fields',
                    'label' => 'navigation.customFields',
                    'icon' => 'puzzle',
                    'route' => '/custom-fields',
                    'permission' => 'custom-fields.view',
                ],
            ],
        ],

        // "Amministrazione": system-level access control and data migration.
        // Separate from the reference-data configuration above (who-can-do-what
        // vs reference values). The section is dropped automatically when the
        // actor can see none of its children.
        [
            'key' => 'administration',
            'label' => 'navigation.administration',
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
                    'key' => 'migrations',
                    // Namespaced i18n key: `migrations` strings live in their own
                    // i18next namespace (see frontend i18n/index.ts).
                    'label' => 'migrations:nav.label',
                    'icon' => 'database-zap',
                    'route' => '/migrations',
                    'permission' => null,
                    'role' => 'super-admin',
                ],
            ],
        ],
    ],

];
