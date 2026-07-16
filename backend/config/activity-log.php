<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Aggregated Activity Log — per-resource registry (spec 0034)
    |--------------------------------------------------------------------------
    |
    | Maps each `{resource}` (GET /api/activity-log/{resource}/{id}) to its
    | root model and the dot-path relations whose OWN activity_log entries are
    | aggregated alongside the root's (provenance kept via subject_type/
    | subject_id — see ActivityLogRegistry/AggregatedActivityService, both
    | fully generic). Adding a resource = one entry here, no controller/service
    | change.
    |
    | v1 covers only `users`, aggregating the personal-data card plus its
    | contacts and addresses (HasPersonalData/HasContacts/HasAddresses).
    |
    */
    'resources' => [
        'users' => [
            'model' => User::class,
            'relations' => ['personalData', 'personalData.contacts', 'personalData.addresses'],
        ],
    ],

];
