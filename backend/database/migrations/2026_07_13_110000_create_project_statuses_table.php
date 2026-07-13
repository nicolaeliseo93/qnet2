<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project status lookup entity (spec 0023): a full-CRUD classification
 * (name/color/sort_order) used by Projects and Campaigns. Named
 * `project_statuses` (not `states`) because that table name is already taken
 * by the geo entity ("Regione"). Referenced with `restrictOnDelete` by both
 * `projects.project_status_id` and `campaigns.project_status_id` (BR-4):
 * defense in depth alongside the service-level 409 guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_statuses');
    }
};
