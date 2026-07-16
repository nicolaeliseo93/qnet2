<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status group lookup entity (spec 0039): a full-CRUD classification
 * (name/color/sort_order) shared by BOTH status configurators
 * (pipeline_statuses, lead_statuses — D-6). `sort_order` here stays a plain
 * manual integer (no drag & drop for groups, unlike the two status tables).
 * `name` is UNIQUE, matching the lead_statuses precedent rather than the
 * pipeline_statuses one, since D-6 explicitly reuses the lead-statuses
 * template.
 *
 * Runs BEFORE the migrations that add `status_group_id` FKs to
 * pipeline_statuses/lead_statuses, which reference this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_groups');
    }
};
