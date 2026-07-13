<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns `attribute_options` with `custom_field_options` (spec 0021 and
 * spec 0017): adds the same color/icon/is_default presentation columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->string('color', 32)->nullable()->after('label');
            $table->string('icon', 191)->nullable()->after('color');
            $table->boolean('is_default')->default(false)->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_options', function (Blueprint $table): void {
            $table->dropColumn(['color', 'icon', 'is_default']);
        });
    }
};
