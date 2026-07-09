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
 * The former "Altro" section attributes (store, categories, payment statuses,
 * ...) are no longer flat columns: they live as universal custom fields
 * (spec 0021), provisioned by QualificaTemplateSeeder. Only `company_id` (the
 * owning società) remains a real column here.
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

            // The owning company (società). The remaining former "Altro"
            // attributes are now universal custom fields (spec 0021,
            // QualificaTemplateSeeder), not flat columns.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

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
