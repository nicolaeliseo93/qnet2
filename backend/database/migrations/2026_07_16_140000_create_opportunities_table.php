<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity entity (spec 0040): a commercial deal against an Anagrafica
 * (`registries`), created manually or generated from a Lead (`lead_id`,
 * nullable, UNIQUE — at most one opportunity per lead, D-2). Every relation
 * is `restrictOnDelete` (BR-3): nothing referenced by an opportunity may be
 * deleted while it exists, mirrored at the app layer by an `abort(409, ...)`
 * guard in each referenced module's Service (Registry/Company/CompanySite/
 * OperationalSite/BusinessFunction/Referent/User/Source/ProductCategory/
 * Lead), mirroring the leads migration's discipline.
 *
 * `name`/`registry_id`/`company_id`/`company_site_id`/`operational_site_id`
 * are NOT NULL (D-4, amendment rev.1 — A-2: the latter 3 were originally
 * optional, promoted to mandatory before this migration was ever committed,
 * hence modified in place rather than a separate alter migration); every
 * other relation is optional. `estimated_value` mirrors the
 * `projects.total_budget`/decimal(15,2) convention. No opportunity-status FK
 * in this iteration (D-3): the schema stays extensible for one added later
 * as a nullable FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->index();
            $table->foreignId('registry_id')->constrained('registries')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_site_id')->constrained('company_sites')->restrictOnDelete();
            $table->foreignId('operational_site_id')->constrained('operational_sites')->restrictOnDelete();
            $table->foreignId('business_function_id')->nullable()->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('referent_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('commercial_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('referents')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('sources')->restrictOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->unique()->constrained('leads')->restrictOnDelete();
            $table->date('start_date')->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->unsignedTinyInteger('success_probability')->nullable();
            $table->timestamps();

            $table->index('registry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
