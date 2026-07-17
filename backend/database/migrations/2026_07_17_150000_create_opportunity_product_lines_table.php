<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0040, amendment rev.3: SUBSTITUTES the single `business_function_id`/
 * `product_category_id` columns on `opportunities` with a one-to-many
 * collection of rows — the same business function may repeat across rows
 * paired with a different product category, but the exact pair is unique.
 * `opportunity_id` cascades (a deleted opportunity drops its own rows);
 * `business_function_id`/`product_category_id` stay restrictOnDelete,
 * mirroring every other FK on this module (BR-3).
 *
 * Best-effort backfill: every opportunity that already had BOTH columns
 * non-null gets exactly one row before the columns are dropped (dev/demo
 * data only — no production data exists yet for this in-progress module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['opportunity_id', 'business_function_id', 'product_category_id'], 'opportunity_product_lines_unique_pair');
            $table->index('business_function_id');
            $table->index('product_category_id');
        });

        $this->backfillFromOpportunities();

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['business_function_id']);
            $table->dropForeign(['product_category_id']);
            $table->dropColumn(['business_function_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('business_function_id')->nullable()->after('operational_site_id')->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('product_category_id')->nullable()->after('source_id')->constrained('product_categories')->restrictOnDelete();
        });

        Schema::dropIfExists('opportunity_product_lines');
    }

    /**
     * One row per opportunity that already carries BOTH values, before the
     * source columns are dropped.
     */
    private function backfillFromOpportunities(): void
    {
        $rows = DB::table('opportunities')
            ->whereNotNull('business_function_id')
            ->whereNotNull('product_category_id')
            ->get(['id', 'business_function_id', 'product_category_id', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            DB::table('opportunity_product_lines')->insert([
                'opportunity_id' => $row->id,
                'business_function_id' => $row->business_function_id,
                'product_category_id' => $row->product_category_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }
};
