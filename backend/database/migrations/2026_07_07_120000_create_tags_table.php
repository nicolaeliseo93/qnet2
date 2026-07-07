<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag lookup entity (spec 0019): a full-CRUD module (id, name) mirroring
 * Source (spec 0018). Unlike Source, a Tag is REUSABLE across entities via
 * the polymorphic `taggables` pivot (see the sibling migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
