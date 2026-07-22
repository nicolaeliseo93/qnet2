<?php

use App\RequestManagement\RequestManagementNotable;

return [

    /*
    |--------------------------------------------------------------------------
    | Notable types (polymorphic allowlist)
    |--------------------------------------------------------------------------
    |
    | Maps the public, stable "entity_type" slug the client sends (the
    | AUTHORIZATION vocabulary — one module = one permission set, spec 0052
    | D-9) to a class-string implementing App\Notes\Contracts\NotableEntity,
    | which declares how the notes component may attach to that module: the
    | host model, the read gate, the mentionable set, the record label and
    | the SPA deep link.
    |
    | Pure data (class-strings only, no closures) — config:cache-safe, same
    | shape as config/attachments.php `attachable_types`. It is also the
    | security boundary for the polymorphic relation: only slugs listed here
    | can be targeted, so a request can never attach a note to — or query —
    | an arbitrary class.
    |
    */

    'notable_types' => [
        'request-management' => RequestManagementNotable::class,
    ],

];
