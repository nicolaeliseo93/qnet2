<?php

namespace Database\Seeders\DemoProductCatalog;

/**
 * Pure data for DemoProductCatalogSeeder (spec 0017): the global attribute
 * catalogue and the category tree, extracted out of the seeder itself
 * (file-size split, engineering.md §6) so the seeder stays a thin loop over
 * this declarative taxonomy.
 *
 * The catalogue models SERVICES (consulting and training), not physical
 * goods: every product is a ProductType::Service.
 *
 * `attributes()` covers text/integer/decimal/boolean/enum (the field types
 * App\CustomFields\FieldTypeRegistry exposes for attributes), enum ones
 * carrying their option list. `tree()` is a nested category structure
 * exercising multi-level
 * inheritance and attribute REUSE (`delivery_mode` is assigned to both
 * `Consulenza` and `Formazione`, never duplicated as an attribute): each
 * node's `attributes` map is `code => is_required` (the category's OWN
 * assignment only — effective/inherited attributes are resolved at read time
 * by CategoryHierarchy, never duplicated here); `products` is a list of demo
 * services for that exact category (generic fields only — products carry no
 * attribute values of their own).
 */
final class ProductCatalogTaxonomy
{
    /**
     * @return array<string, array{name: string, type: string, options?: array<int, string>}>
     */
    public static function attributes(): array
    {
        return [
            'provider' => ['name' => 'Provider', 'type' => 'text'],
            'sla_hours' => ['name' => 'SLA response (hours)', 'type' => 'decimal'],
            'delivery_mode' => ['name' => 'Delivery mode', 'type' => 'enum', 'options' => ['Onsite', 'Remoto', 'Ibrido']],
            'seniority_level' => ['name' => 'Seniority level', 'type' => 'enum', 'options' => ['Junior', 'Senior', 'Lead']],
            'duration_hours' => ['name' => 'Duration (hours)', 'type' => 'integer'],
            'technology' => ['name' => 'Technology stack', 'type' => 'text'],
            'on_call' => ['name' => 'On-call support', 'type' => 'boolean'],
            'audit_type' => ['name' => 'Audit type', 'type' => 'enum', 'options' => ['Black-box', 'White-box', 'Grey-box']],
            'is_remote' => ['name' => 'Remote', 'type' => 'boolean'],
            'certificate_included' => ['name' => 'Certificate included', 'type' => 'boolean'],
            'max_participants' => ['name' => 'Max participants', 'type' => 'integer'],
            'session_length_hours' => ['name' => 'Session length (hours)', 'type' => 'decimal'],
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
                            ],
                        ],
                        'children' => [
                            'Sviluppo Software' => [
                                'attributes' => ['technology' => true, 'on_call' => false],
                                'products' => [
                                    [
                                        'name' => 'Sviluppo Applicazione Web', 'cost' => 5000, 'price' => 9000,
                                    ],
                                    [
                                        'name' => 'Manutenzione Software Evolutiva', 'cost' => 1500, 'price' => 2900,
                                    ],
                                    [
                                        'name' => 'Migrazione Cloud', 'cost' => 3000, 'price' => 5500,
                                    ],
                                ],
                            ],
                            'Cybersecurity' => [
                                'attributes' => ['audit_type' => false],
                                'products' => [
                                    [
                                        'name' => 'Penetration Test', 'cost' => 2000, 'price' => 4000,
                                    ],
                                    [
                                        'name' => 'Security Audit Applicativo', 'cost' => 1200, 'price' => 2400,
                                    ],
                                    [
                                        'name' => 'Vulnerability Assessment', 'cost' => 800, 'price' => 1600,
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
                            ],
                            [
                                'name' => 'Analisi di Processo', 'cost' => 1500, 'price' => 3000,
                            ],
                            [
                                'name' => 'Business Plan Review', 'cost' => 900, 'price' => 1800,
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
                            ],
                            [
                                'name' => 'Corso Cybersecurity Base', 'cost' => 400, 'price' => 900,
                            ],
                            [
                                'name' => 'Corso Project Management', 'cost' => 350, 'price' => 800,
                            ],
                        ],
                    ],
                    'Workshop' => [
                        'attributes' => ['session_length_hours' => true, 'max_participants' => true],
                        'products' => [
                            [
                                'name' => 'Workshop Agile', 'cost' => 300, 'price' => 700,
                            ],
                            [
                                'name' => 'Workshop DevOps', 'cost' => 400, 'price' => 950,
                            ],
                            [
                                'name' => 'Workshop UX Design', 'cost' => 350, 'price' => 850,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
