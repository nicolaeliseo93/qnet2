<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns `attributes` with the custom-fields presentation shape (spec 0021
 * and spec 0017): `data_type` (STRING/INTEGER/DECIMAL/BOOLEAN/ENUM) becomes
 * `type`, whose values are now App\CustomFields\FieldTypeRegistry keys, plus
 * the same description/help_text/placeholder/icon/config/relation_target
 * columns already carried by `custom_field_definitions`.
 *
 * Column order (rename, then add) keeps both up() and down() valid on
 * SQLite (dev) and MySQL (prod) without doctrine/dbal.
 */
return new class extends Migration
{
    private const TYPE_MAP = [
        'STRING' => 'text',
        'INTEGER' => 'integer',
        'DECIMAL' => 'decimal',
        'BOOLEAN' => 'boolean',
        'ENUM' => 'enum',
    ];

    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table): void {
            $table->renameColumn('data_type', 'type');
        });

        Schema::table('attributes', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('type');
            $table->text('help_text')->nullable()->after('description');
            $table->string('placeholder', 191)->nullable()->after('help_text');
            $table->string('icon', 191)->nullable()->after('placeholder');
            $table->json('config')->nullable()->after('icon');
            $table->json('relation_target')->nullable()->after('config');
        });

        foreach (self::TYPE_MAP as $legacy => $current) {
            DB::table('attributes')->where('type', $legacy)->update(['type' => $current]);
        }
    }

    public function down(): void
    {
        // Types with no legacy counterpart (textarea/relation/date/...) are
        // not representable by the old enum: collapse them to STRING first,
        // before the loop below rewrites the known ones to legacy values (a
        // legacy value like STRING is never itself a FieldTypeRegistry key,
        // so running this after would collapse everything to STRING).
        DB::table('attributes')
            ->whereNotIn('type', array_values(self::TYPE_MAP))
            ->update(['type' => 'STRING']);

        foreach (self::TYPE_MAP as $legacy => $current) {
            DB::table('attributes')->where('type', $current)->update(['type' => $legacy]);
        }

        Schema::table('attributes', function (Blueprint $table): void {
            $table->dropColumn(['description', 'help_text', 'placeholder', 'icon', 'config', 'relation_target']);
        });

        Schema::table('attributes', function (Blueprint $table): void {
            $table->renameColumn('type', 'data_type');
        });
    }
};
