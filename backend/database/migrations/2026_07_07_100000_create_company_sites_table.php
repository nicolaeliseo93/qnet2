<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company site entity (spec 0020 — "Società Sedi"): a flexible site
 * anagraphic under a Company. Its contacts + address live on a polymorphic
 * personal-data card (`HasPersonalData`, morph `personable`), NOT as flat
 * columns on this table — mirroring the Registry module. A logo (polymorphic
 * `attachments`, HasAttachments) and an owned bank list (`company_site_banks`,
 * real FK) complete it. `old_id` is additive (spec 0013 external migration),
 * declared inline since this table is greenfield.
 *
 * `default_bank_id` is intentionally NOT foreign-keyed here: its target
 * table (`company_site_banks`) does not exist yet (created right after this
 * one — the two tables reference each other). The constraint is added by
 * `create_company_site_banks_table`, once both tables exist.
 *
 * The "Altro" section columns (company_id .. surface_sqm) are read-only in
 * this slice (enforced by CompanySitesAuthorization/EnforcesFieldPermissions,
 * not the schema): plain data columns, FK only where the target table exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_sites', function (Blueprint $table) {
            $table->id();
            // `after('id')` is an ALTER-TABLE-only modifier (add_old_id_to_*
            // migrations use it for that reason); in a fresh CREATE TABLE the
            // column position is simply where it is declared.
            $table->unsignedBigInteger('old_id')->nullable();
            $table->unique('old_id');

            // Profilo. The site's own display name (NOT derived from the card,
            // unlike Registry). Contacts + address live on the personal-data
            // card (morph `personable`), not here.
            $table->string('name', 191);
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false)->index();

            // Impostazioni.
            $table->foreignId('responsible_rda_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_tickets_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_validation_contracts_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_validation_contracts_two_id')->nullable()->constrained('users')->nullOnDelete();
            // FK added by create_company_site_banks_table (see class docblock).
            $table->unsignedBigInteger('default_bank_id')->nullable();
            $table->integer('proforma_progressive')->nullable();
            $table->integer('invoice_progressive')->nullable();
            $table->bigInteger('quotation_layout_id')->nullable();
            $table->bigInteger('quotation_header_id')->nullable();
            $table->bigInteger('quotation_footer_id')->nullable();

            // Altro (read-only for now — see class docblock).
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('accounting_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('store_id')->nullable();
            $table->smallInteger('company_type')->nullable();
            $table->tinyInteger('commissions')->nullable();
            $table->integer('order_sites')->nullable();
            $table->smallInteger('payment_status_assign_technician')->nullable();
            $table->smallInteger('payment_status_deposit')->nullable();
            $table->smallInteger('payment_status_balance')->nullable();
            $table->bigInteger('default_payment_id')->nullable();
            $table->bigInteger('default_vat_id')->nullable();

            // Category-like references (no target table yet): nullable bigint,
            // no FK, mirrors quotation_*/default_payment_id/default_vat_id above.
            foreach ([
                'other_category_id', 'iso_category_id', 'soa_category_id', 'sic_category_id',
                'avv_category_id', 'gdpr_category_id', 'res_category_id', 'pal_category_id',
                'quattro_category_id', 'finage_category_id', 'fondi_category_id', 'gare_category_id',
                'partnership_category_id', 'progetti_category_id',
            ] as $categoryColumn) {
                $table->bigInteger($categoryColumn)->nullable();
            }

            $table->smallInteger('status')->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('surface_sqm')->nullable();

            $table->timestamps();

            // Grid search/sort (spec 0020 contract: searchable name, default
            // sort created_at). Email/vat_number are no longer real columns
            // (they live on the personal-data card / its contacts).
            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_sites');
    }
};
