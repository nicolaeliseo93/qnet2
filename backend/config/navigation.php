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
            'key' => 'management',
            'label' => 'navigation.management',
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
            ],
        ],
    ],

];
