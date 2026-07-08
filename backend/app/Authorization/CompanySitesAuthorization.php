<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `company-sites` resource (spec 0020).
 *
 * The "Altro" section (OTHER_FIELDS) is read-only for EVERY actor, regardless
 * of write ability — unlike every other field here, whose ceiling is the
 * usual visible+editable-when-may-write / visible+readonly-otherwise
 * (mirrors CompaniesAuthorization). `quotation_layout_id`/`quotation_header_id`/
 * `quotation_footer_id` are `settings`-group fields but ALSO always read-only
 * (no target table yet, spec 0020) — the one exception inside an otherwise
 * writable group.
 */
class CompanySitesAuthorization extends AbstractResourceAuthorization
{
    /**
     * The read-only "Altro" section: field key -> form `type` hint. Shared by
     * fields() (catalogue) and fieldPermissionCeiling() (always readonly).
     *
     * @var array<string, string>
     */
    private const array OTHER_FIELDS = [
        'accounting_manager_id' => 'select',
        'store_id' => 'number',
        'company_type' => 'number',
        'commissions' => 'number',
        'order_sites' => 'number',
        'payment_status_assign_technician' => 'number',
        'payment_status_deposit' => 'number',
        'payment_status_balance' => 'number',
        'default_payment_id' => 'number',
        'default_vat_id' => 'number',
        'other_category_id' => 'number',
        'iso_category_id' => 'number',
        'soa_category_id' => 'number',
        'sic_category_id' => 'number',
        'avv_category_id' => 'number',
        'gdpr_category_id' => 'number',
        'res_category_id' => 'number',
        'pal_category_id' => 'number',
        'quattro_category_id' => 'number',
        'finage_category_id' => 'number',
        'fondi_category_id' => 'number',
        'gare_category_id' => 'number',
        'partnership_category_id' => 'number',
        'progetti_category_id' => 'number',
        'status' => 'number',
        'color' => 'text',
        'surface_sqm' => 'number',
    ];

    /**
     * `settings`-group fields with NO target table yet (spec 0020): always
     * read-only, unlike the rest of `settings`.
     *
     * @var array<int, string>
     */
    private const array READONLY_SETTINGS_FIELDS = ['quotation_layout_id', 'quotation_header_id', 'quotation_footer_id'];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'company-sites';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        $fields = [
            new FieldDefinition('name', 'text', 'profile', mandatory: true),
            new FieldDefinition('notes', 'textarea', 'profile'),
            new FieldDefinition('logo', 'image', 'profile'),
            // The nested personal-data card (contacts + address), mirroring
            // RegistriesAuthorization verbatim (dot-path shape of the write
            // payload, spec 0008). `type` is always `company` here, but the
            // catalogue keeps the full key set in lockstep with the shared
            // ValidatesUserProfile surface, exactly like Registry.
            new FieldDefinition('personal_data.type', 'select', 'personal_data'),
            new FieldDefinition('personal_data.company_name', 'text', 'personal_data'),
            new FieldDefinition('personal_data.tax_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.vat_number', 'text', 'personal_data'),
            new FieldDefinition('personal_data.sdi_code', 'text', 'personal_data'),
            new FieldDefinition('personal_data.contacts', 'collection', 'personal_data'),
            new FieldDefinition('personal_data.addresses', 'collection', 'personal_data'),
            new FieldDefinition('company_id', 'select', 'settings'),
            new FieldDefinition('responsible_rda_id', 'select', 'settings'),
            new FieldDefinition('responsible_tickets_id', 'select', 'settings'),
            new FieldDefinition('responsible_validation_contracts_id', 'select', 'settings'),
            new FieldDefinition('responsible_validation_contracts_two_id', 'select', 'settings'),
            new FieldDefinition('default_bank_id', 'select', 'settings'),
            new FieldDefinition('proforma_progressive', 'number', 'settings'),
            new FieldDefinition('invoice_progressive', 'number', 'settings'),
            new FieldDefinition('quotation_layout_id', 'number', 'settings'),
            new FieldDefinition('quotation_header_id', 'number', 'settings'),
            new FieldDefinition('quotation_footer_id', 'number', 'settings'),
            new FieldDefinition('banks', 'collection', 'banks'),
        ];

        foreach (self::OTHER_FIELDS as $key => $type) {
            $fields[] = new FieldDefinition($key, $type, 'other');
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'upload_logo', 'delete_logo', 'set_default'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $ceiling = [
            'name' => $this->writableOrReadonly($actor, $model, required: true),
            'notes' => $this->writableOrReadonly($actor, $model),
            'logo' => $this->writableOrReadonly($actor, $model),
            'personal_data.type' => $this->writableOrReadonly($actor, $model),
            'personal_data.company_name' => $this->writableOrReadonly($actor, $model),
            'personal_data.tax_code' => $this->writableOrReadonly($actor, $model),
            'personal_data.vat_number' => $this->writableOrReadonly($actor, $model),
            'personal_data.sdi_code' => $this->writableOrReadonly($actor, $model),
            'personal_data.contacts' => $this->writableOrReadonly($actor, $model),
            'personal_data.addresses' => $this->writableOrReadonly($actor, $model),
            'company_id' => $this->writableOrReadonly($actor, $model),
            'responsible_rda_id' => $this->writableOrReadonly($actor, $model),
            'responsible_tickets_id' => $this->writableOrReadonly($actor, $model),
            'responsible_validation_contracts_id' => $this->writableOrReadonly($actor, $model),
            'responsible_validation_contracts_two_id' => $this->writableOrReadonly($actor, $model),
            'default_bank_id' => $this->writableOrReadonly($actor, $model),
            'proforma_progressive' => $this->writableOrReadonly($actor, $model),
            'invoice_progressive' => $this->writableOrReadonly($actor, $model),
            'banks' => $this->writableOrReadonly($actor, $model),
        ];

        foreach (self::READONLY_SETTINGS_FIELDS as $key) {
            $ceiling[$key] = FieldPermission::visibleReadonly();
        }

        foreach (array_keys(self::OTHER_FIELDS) as $key) {
            // "Altro": read-only ceiling always — the write path
            // (StoreCompanySiteRequest/UpdateCompanySiteRequest) never accepts
            // these keys either.
            $ceiling[$key] = FieldPermission::visibleReadonly();
        }

        return $ceiling;
    }

    private function writableOrReadonly(User $actor, ?Model $model, bool $required = false): FieldPermission
    {
        return $this->actorMayWrite($actor, $model)
            ? FieldPermission::visibleEditable(required: $required)
            : FieldPermission::visibleReadonly();
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $actor->can('company-sites.delete'),
            'export' => $actor->can('company-sites.export'),
            'import' => $actor->can('company-sites.import'),
            // Logo/set-default are gated by the resource's own `update` ability,
            // mirroring UsersAuthorization::actionPermissions (upload_avatar).
            'upload_logo' => $model !== null && $actor->can('company-sites.update'),
            'delete_logo' => $model !== null && $actor->can('company-sites.update'),
            'set_default' => $model !== null && $actor->can('company-sites.update'),
        ];
    }
}
