<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leads', 'lead_status_id')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->dropForeign(['lead_status_id']);
                $table->dropColumn('lead_status_id');
            });
        }

        Schema::dropIfExists('lead_statuses');
    }

    public function down(): void
    {
        if (! Schema::hasTable('lead_statuses')) {
            Schema::create('lead_statuses', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 191)->unique();
                $table->string('color', 32)->nullable();
                $table->integer('sort_order')->default(0);
                $table->string('system_key', 16)->nullable()->unique();
                $table->string('group', 16)->default('open');
                $table->timestamps();
            });
        }

        $defaultStatusId = DB::table('lead_statuses')->insertGetId([
            'name' => 'New',
            'color' => 'slate',
            'sort_order' => 0,
            'system_key' => 'new',
            'group' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! Schema::hasColumn('leads', 'lead_status_id')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->foreignId('lead_status_id')
                    ->nullable()
                    ->after('source_id')
                    ->constrained('lead_statuses')
                    ->restrictOnDelete();
            });
        }

        DB::table('leads')->whereNull('lead_status_id')->update(['lead_status_id' => $defaultStatusId]);

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('lead_status_id')->nullable(false)->change();
        });
    }
};
