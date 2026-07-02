<?php

use App\Enums\ContactTypeEnum;
use App\Enums\LocaleEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\PersonalTitleEnum;

return [

    /*
    |--------------------------------------------------------------------------
    | Public application bootstrap config (GET /api/config)
    |--------------------------------------------------------------------------
    |
    | Server-side allowlist of the metadata exposed by the PUBLIC bootstrap
    | endpoint GET /api/config (outside auth:sanctum). ConfigService iterates
    | this list and serializes each enum's presentation options for the client.
    |
    | ⚠️  SECURITY — THIS ENDPOINT IS PUBLIC (UNAUTHENTICATED).
    |     - Only add NON-SENSITIVE presentation metadata here.
    |     - Never add anything user-, tenant-, or permission-scoped, nor any
    |       value that leaks internal structure beyond what the login screen
    |       legitimately needs before authentication.
    |     - This is a fixed server-side list, never built from request input:
    |       no reflection over user input is performed downstream.
    |     - ANY addition to this file REQUIRES a Security Agent review.
    |
    | Shape:
    |   'form_enums' => [ '<snake_case key>' => <BackedEnum>::class, ... ]
    | The key becomes the JSON key under data.enums; the value is the enum FQCN
    | whose ::options() (minus #[HiddenOnForm] cases) is serialized.
    |
    */

    'form_enums' => [
        // Supported UI/user locales (NON-SENSITIVE): the login screen and the
        // user/profile forms render their language select from this list instead
        // of hardcoding it on the frontend.
        'locale' => LocaleEnum::class,
        'personal_data_type' => PersonalDataTypeEnum::class,
        'personal_title' => PersonalTitleEnum::class,
        'contact_type' => ContactTypeEnum::class,
        'notification_level' => NotificationLevelEnum::class,
    ],

];
