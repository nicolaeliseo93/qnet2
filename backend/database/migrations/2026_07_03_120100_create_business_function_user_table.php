<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the business function <-> associated users relation (spec 0010).
 * Both sides cascade: deleting either the function or the user drops the
 * membership row, no orphaned pivot data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_function_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_function_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['business_function_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_function_user');
    }
};
