<?php

namespace Database\Seeders;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Seeder;

/**
 * Clean, idempotent reference seed: provisions the company-site "Qualifica"
 * template as universal custom field definitions (spec 0021) for the
 * `company-sites` entity_type. These fields were formerly the flat "Altro"
 * columns on `company_sites`; they are now dynamic custom fields, so the
 * template lives here (as data) instead of the schema.
 *
 * Definitions only — no values are written (that is per-site user data).
 * `updateOrCreate` on (entity_type, key) keeps re-runs from duplicating.
 */
class QualificaTemplateSeeder extends Seeder
{
    private const string ENTITY_TYPE = 'company-sites';

    /**
     * The template fields in display order: [key, label, type]. Everything is
     * `integer` (the former numeric reference/status columns) except `color`
     * (free text) and `accounting_manager_id`, a one-to-one relation to a user.
     *
     * @var list<array{key: string, label: string, type: string}>
     */
    private const array FIELDS = [
        ['key' => 'accounting_manager_id', 'label' => 'Responsabile amministrativo', 'type' => 'relation'],
        ['key' => 'store_id', 'label' => 'Negozio', 'type' => 'integer'],
        ['key' => 'company_type', 'label' => 'Tipo società', 'type' => 'integer'],
        ['key' => 'commissions', 'label' => 'Commissioni', 'type' => 'integer'],
        ['key' => 'order_sites', 'label' => 'Ordine sedi', 'type' => 'integer'],
        ['key' => 'payment_status_assign_technician', 'label' => 'Stato pagamento (assegna tecnico)', 'type' => 'integer'],
        ['key' => 'payment_status_deposit', 'label' => 'Stato pagamento (acconto)', 'type' => 'integer'],
        ['key' => 'payment_status_balance', 'label' => 'Stato pagamento (saldo)', 'type' => 'integer'],
        ['key' => 'default_payment_id', 'label' => 'Pagamento predefinito', 'type' => 'integer'],
        ['key' => 'default_vat_id', 'label' => 'IVA predefinita', 'type' => 'integer'],
        ['key' => 'other_category_id', 'label' => 'Categoria altro', 'type' => 'integer'],
        ['key' => 'iso_category_id', 'label' => 'Categoria ISO', 'type' => 'integer'],
        ['key' => 'soa_category_id', 'label' => 'Categoria SOA', 'type' => 'integer'],
        ['key' => 'sic_category_id', 'label' => 'Categoria SIC', 'type' => 'integer'],
        ['key' => 'avv_category_id', 'label' => 'Categoria AVV', 'type' => 'integer'],
        ['key' => 'gdpr_category_id', 'label' => 'Categoria GDPR', 'type' => 'integer'],
        ['key' => 'res_category_id', 'label' => 'Categoria RES', 'type' => 'integer'],
        ['key' => 'pal_category_id', 'label' => 'Categoria PAL', 'type' => 'integer'],
        ['key' => 'quattro_category_id', 'label' => 'Categoria 4.0', 'type' => 'integer'],
        ['key' => 'finage_category_id', 'label' => 'Categoria Finage', 'type' => 'integer'],
        ['key' => 'fondi_category_id', 'label' => 'Categoria fondi', 'type' => 'integer'],
        ['key' => 'gare_category_id', 'label' => 'Categoria gare', 'type' => 'integer'],
        ['key' => 'partnership_category_id', 'label' => 'Categoria partnership', 'type' => 'integer'],
        ['key' => 'progetti_category_id', 'label' => 'Categoria progetti', 'type' => 'integer'],
        ['key' => 'status', 'label' => 'Stato', 'type' => 'integer'],
        ['key' => 'color', 'label' => 'Colore', 'type' => 'text'],
        ['key' => 'surface_sqm', 'label' => 'Superficie (mq)', 'type' => 'integer'],
    ];

    /**
     * `accounting_manager_id` points at a single user (the former
     * `accounting_manager_id` FK). `users` is a registered custom-fieldable
     * entity, so it is a valid relation target + for-select resource.
     *
     * @var array<string, mixed>
     */
    private const array MANAGER_RELATION_TARGET = [
        'entity_type' => 'users',
        'cardinality' => 'one',
        'for_select_resource' => 'users',
    ];

    public function run(): void
    {
        $sortOrder = 0;

        foreach (self::FIELDS as $field) {
            CustomFieldDefinition::updateOrCreate(
                ['entity_type' => self::ENTITY_TYPE, 'key' => $field['key']],
                [
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'sort_order' => $sortOrder++,
                    'is_indexed' => false,
                    'is_active' => true,
                    'relation_target' => $field['type'] === 'relation' ? self::MANAGER_RELATION_TARGET : null,
                ],
            );
        }
    }
}
