<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business function entity (spec 0010): a name, a mutually-exclusive type
 * (business unit XOR business service XOR neither, modelled as two booleans
 * so the SSRM grid can filter/sort each independently) and an optional
 * manager. Associated users live in the `business_function_user` pivot
 * (see the next migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_functions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->boolean('is_business_unit')->default(false);
            $table->boolean('is_business_service')->default(false);

            // Optional single manager. nullOnDelete: removing the user just
            // clears the manager, it never cascades to deleting the function.
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Grid search/sort on name; default sort on created_at (spec 0010).
            $table->index('name');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_functions');
    }
};
