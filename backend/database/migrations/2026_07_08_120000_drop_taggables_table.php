<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the polymorphic `taggables` pivot (spec 0019). The Tag/Sector
 * association is retired: Sector was its only producer and the tagging of
 * sectors is being removed at every layer. Tag remains a standalone lookup
 * (its own table/CRUD/import are untouched). `down()` recreates the pivot in
 * its original shape so the migration is fully reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('taggables');
    }

    public function down(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');

            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_id', 'taggable_type']);
        });
    }
};
