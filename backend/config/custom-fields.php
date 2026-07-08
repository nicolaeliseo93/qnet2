<?php

use App\CustomFields\Types\BooleanFieldType;
use App\CustomFields\Types\DecimalFieldType;
use App\CustomFields\Types\EnumFieldType;
use App\CustomFields\Types\IntegerFieldType;
use App\CustomFields\Types\RelationFieldType;
use App\CustomFields\Types\TextareaFieldType;
use App\CustomFields\Types\TextFieldType;

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Field Type Registry (spec 0021)
    |--------------------------------------------------------------------------
    |
    | Maps each custom field `type` string to its FieldTypeHandler. One
    | strategy per type owns storage/validation/normalization/filter/sort/meta
    | for every definition of that type, across every custom-fieldable domain.
    | App\CustomFields\FieldTypeRegistry resolves the class below through the
    | container.
    |
    | Adding a type = 1 handler class implementing
    | App\CustomFields\Types\FieldTypeHandler + 1 line here (OCP) — no change
    | to any decorator, controller, or the write pipeline.
    */
    'types' => [
        'text' => TextFieldType::class,
        'textarea' => TextareaFieldType::class,
        'integer' => IntegerFieldType::class,
        'decimal' => DecimalFieldType::class,
        'boolean' => BooleanFieldType::class,
        'enum' => EnumFieldType::class,
        'relation' => RelationFieldType::class,
    ],

];
