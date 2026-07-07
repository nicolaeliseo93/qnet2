<?php

namespace Database\Seeders\DemoProductCatalog;

use App\Enums\AttributeType;

/**
 * Pure data for DemoProductCatalogSeeder (spec 0017): the global attribute
 * catalogue and the category tree, extracted out of the seeder itself
 * (file-size split, engineering.md §6) so the seeder stays a thin loop over
 * this declarative taxonomy.
 *
 * The catalogue models SERVICES (consulting and training), not physical
 * goods: every product is a ProductType::Service.
 *
 * `attributes()` covers all 5 data types, ENUM ones carrying their option
 * list. `tree()` is a nested category structure exercising multi-level
 * inheritance and attribute REUSE (`delivery_mode` is assigned to both
 * `Consulenza` and `Formazione`, never duplicated as an attribute): each
 * node's `attributes` map is `code => is_required` (the category's OWN
 * assignment only — effective/inherited attributes are resolved at read time
 * by CategoryHierarchy, never duplicated here); `products` is a list of demo
 * services for that exact category, each carrying a value for every one of
 * its EFFECTIVE attributes (own + every ancestor's), keyed by attribute code
 * (ENUM values are the option's `value` string, resolved to its option_id by
 * ProductAttributeValueWriter — never an id here).
 */
final class ProductCatalogTaxonomy
{
    /**
     * @return array<string, array{name: string, type: AttributeType, options?: array<int, string>}>
     */
    public static function attributes(): array
    {
        return [
            'provider' => ['name' => 'Provider', 'type' => AttributeType::String],
            'sla_hours' => ['name' => 'SLA response (hours)', 'type' => AttributeType::Decimal],
            'delivery_mode' => ['name' => 'Delivery mode', 'type' => AttributeType::Enum, 'options' => ['Onsite', 'Remoto', 'Ibrido']],
            'seniority_level' => ['name' => 'Seniority level', 'type' => AttributeType::Enum, 'options' => ['Junior', 'Senior', 'Lead']],
            'duration_hours' => ['name' => 'Duration (hours)', 'type' => AttributeType::Integer],
            'technology' => ['name' => 'Technology stack', 'type' => AttributeType::String],
            'on_call' => ['name' => 'On-call support', 'type' => AttributeType::Boolean],
            'audit_type' => ['name' => 'Audit type', 'type' => AttributeType::Enum, 'options' => ['Black-box', 'White-box', 'Grey-box']],
            'is_remote' => ['name' => 'Remote', 'type' => AttributeType::Boolean],
            'certificate_included' => ['name' => 'Certificate included', 'type' => AttributeType::Boolean],
            'max_participants' => ['name' => 'Max participants', 'type' => AttributeType::Integer],
            'session_length_hours' => ['name' => 'Session length (hours)', 'type' => AttributeType::Decimal],
        ];
    }

    /**
     * @return array<string, array{attributes: array<string, bool>, products: array<int, array<string, mixed>>, children?: array<string, mixed>}>
     */
    public static function tree(): array
    {
        return [
            'Consulenza' => [
                'attributes' => ['provider' => false, 'sla_hours' => false, 'delivery_mode' => false],
                'products' => [],
                'children' => [
                    'IT' => [
                        'attributes' => ['seniority_level' => true, 'duration_hours' => false],
                        'products' => [
                            [
                                'name' => 'IT Assessment Iniziale',
                                'description' => 'Valutazione dello stato dei sistemi e roadmap tecnica.',
                                'cost' => 400, 'price' => 800,
                                'values' => ['provider' => 'Accenture', 'sla_hours' => 8.0, 'delivery_mode' => 'Ibrido', 'seniority_level' => 'Senior', 'duration_hours' => 40],
                            ],
                        ],
                        'children' => [
                            'Sviluppo Software' => [
                                'attributes' => ['technology' => true, 'on_call' => false],
                                'products' => [
                                    [
                                        'name' => 'Sviluppo Applicazione Web', 'cost' => 5000, 'price' => 9000,
                                        'values' => ['provider' => 'Deloitte', 'sla_hours' => 4.0, 'delivery_mode' => 'Remoto', 'seniority_level' => 'Senior', 'duration_hours' => 320, 'technology' => 'Laravel/React', 'on_call' => false],
                                    ],
                                    [
                                        'name' => 'Manutenzione Software Evolutiva', 'cost' => 1500, 'price' => 2900,
                                        'values' => ['provider' => 'Reply', 'sla_hours' => 8.0, 'delivery_mode' => 'Ibrido', 'seniority_level' => 'Junior', 'duration_hours' => 120, 'technology' => 'PHP', 'on_call' => true],
                                    ],
                                    [
                                        'name' => 'Migrazione Cloud', 'cost' => 3000, 'price' => 5500,
                                        'values' => ['provider' => 'Accenture', 'sla_hours' => 2.0, 'delivery_mode' => 'Onsite', 'seniority_level' => 'Lead', 'duration_hours' => 200, 'technology' => 'AWS', 'on_call' => true],
                                    ],
                                ],
                            ],
                            'Cybersecurity' => [
                                'attributes' => ['audit_type' => false],
                                'products' => [
                                    [
                                        'name' => 'Penetration Test', 'cost' => 2000, 'price' => 4000,
                                        'values' => ['provider' => 'IBM', 'sla_hours' => 6.0, 'delivery_mode' => 'Remoto', 'seniority_level' => 'Lead', 'duration_hours' => 80, 'audit_type' => 'Black-box'],
                                    ],
                                    [
                                        'name' => 'Security Audit Applicativo', 'cost' => 1200, 'price' => 2400,
                                        'values' => ['provider' => 'Deloitte', 'sla_hours' => 12.0, 'delivery_mode' => 'Ibrido', 'seniority_level' => 'Senior', 'duration_hours' => 60, 'audit_type' => 'White-box'],
                                    ],
                                    [
                                        'name' => 'Vulnerability Assessment', 'cost' => 800, 'price' => 1600,
                                        'values' => ['provider' => 'Reply', 'sla_hours' => 24.0, 'delivery_mode' => 'Remoto', 'seniority_level' => 'Junior', 'duration_hours' => 40, 'audit_type' => 'Grey-box'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Business' => [
                        'attributes' => ['seniority_level' => true, 'duration_hours' => false, 'is_remote' => false],
                        'products' => [
                            [
                                'name' => 'Consulenza Strategica', 'cost' => 3000, 'price' => 6000,
                                'values' => ['provider' => 'McKinsey', 'sla_hours' => 24.0, 'delivery_mode' => 'Onsite', 'seniority_level' => 'Lead', 'duration_hours' => 100, 'is_remote' => false],
                            ],
                            [
                                'name' => 'Analisi di Processo', 'cost' => 1500, 'price' => 3000,
                                'values' => ['provider' => 'BCG', 'sla_hours' => 48.0, 'delivery_mode' => 'Ibrido', 'seniority_level' => 'Senior', 'duration_hours' => 80, 'is_remote' => true],
                            ],
                            [
                                'name' => 'Business Plan Review', 'cost' => 900, 'price' => 1800,
                                'values' => ['provider' => 'Deloitte', 'sla_hours' => 48.0, 'delivery_mode' => 'Remoto', 'seniority_level' => 'Junior', 'duration_hours' => 40, 'is_remote' => true],
                            ],
                        ],
                    ],
                ],
            ],
            'Formazione' => [
                'attributes' => ['delivery_mode' => false, 'certificate_included' => false],
                'products' => [],
                'children' => [
                    'Corsi' => [
                        'attributes' => ['duration_hours' => false, 'max_participants' => false],
                        'products' => [
                            [
                                'name' => 'Corso Laravel Avanzato', 'cost' => 500, 'price' => 1200,
                                'values' => ['delivery_mode' => 'Remoto', 'certificate_included' => true, 'duration_hours' => 32, 'max_participants' => 20],
                            ],
                            [
                                'name' => 'Corso Cybersecurity Base', 'cost' => 400, 'price' => 900,
                                'values' => ['delivery_mode' => 'Ibrido', 'certificate_included' => true, 'duration_hours' => 24, 'max_participants' => 15],
                            ],
                            [
                                'name' => 'Corso Project Management', 'cost' => 350, 'price' => 800,
                                'values' => ['delivery_mode' => 'Onsite', 'certificate_included' => false, 'duration_hours' => 16, 'max_participants' => 25],
                            ],
                        ],
                    ],
                    'Workshop' => [
                        'attributes' => ['session_length_hours' => true, 'max_participants' => true],
                        'products' => [
                            [
                                'name' => 'Workshop Agile', 'cost' => 300, 'price' => 700,
                                'values' => ['delivery_mode' => 'Onsite', 'certificate_included' => false, 'session_length_hours' => 8.0, 'max_participants' => 12],
                            ],
                            [
                                'name' => 'Workshop DevOps', 'cost' => 400, 'price' => 950,
                                'values' => ['delivery_mode' => 'Ibrido', 'certificate_included' => true, 'session_length_hours' => 6.5, 'max_participants' => 10],
                            ],
                            [
                                'name' => 'Workshop UX Design', 'cost' => 350, 'price' => 850,
                                'values' => ['delivery_mode' => 'Remoto', 'certificate_included' => false, 'session_length_hours' => 4.0, 'max_participants' => 15],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
