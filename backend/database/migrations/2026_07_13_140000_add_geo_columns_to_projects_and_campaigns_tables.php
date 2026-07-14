<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geographic scope cascade (spec 0027): adds `country_id`, `province_id` and
 * `city_id` to `projects` and `campaigns`, completing the existing `state_id`
 * column into the full Country -> State -> Province -> City chain already used
 * by the address module (same FK targets, same nullable + nullOnDelete
 * convention: losing the referenced row must never delete the project/
 * campaign it belongs to).
 *
 * The columns stay nullable at the schema level regardless of the "country is
 * required" business rule (D-4): `country_id` is enforced at the FormRequest
 * layer only, so existing rows (demo data, legacy imports) with no country do
 * not break this migration. The parent/child consistency of the hierarchy
 * (BR-4) is likewise a write-pipeline concern, not a DB constraint.
 *
 * Indexed on `country_id` and `city_id` on both tables: the projects table
 * filters/sorts on the four geo levels as derived columns, and the campaigns
 * table filters on the merged effective geo starting from these same columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('registry_id')->constrained('countries')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->after('state_id')->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('province_id')->constrained('cities')->nullOnDelete();

            $table->index('country_id');
            $table->index('city_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('partner_id')->constrained('countries')->nullOnDelete();
            $table->foreignId('province_id')->nullable()->after('state_id')->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('province_id')->constrained('cities')->nullOnDelete();

            $table->index('country_id');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['province_id']);
            $table->dropForeign(['city_id']);
            $table->dropIndex(['country_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn(['country_id', 'province_id', 'city_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['province_id']);
            $table->dropForeign(['city_id']);
            $table->dropIndex(['country_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn(['country_id', 'province_id', 'city_id']);
        });
    }
};
