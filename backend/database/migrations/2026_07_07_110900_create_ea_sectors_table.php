<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EA sector tree (spec 0018): unlimited-depth parent/child hierarchy, a
 * lookup used to classify Anagrafiche (no such relation yet — see spec
 * 0018 scope). `parent_id` restricts on delete (restrictOnDelete) — a
 * sector with children must be reparented/deleted first, mirroring the
 * service-level restrictive-delete guard (EaSectorService::delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ea_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->foreignId('parent_id')->nullable()->constrained('ea_sectors')->restrictOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_sectors');
    }
};
