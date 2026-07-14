<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead status lookup entity (spec 0029): a full-CRUD classification
 * (name/color/sort_order) for Leads, 1:1 in shape with `project_statuses`
 * (spec 0023). `name` is UNIQUE (D-4), unlike project_statuses: a novelty for
 * this lookup module. Referenced by `leads.lead_status_id` with
 * `restrictOnDelete` (BR-3): defense in depth alongside the service-level
 * 409 guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};
