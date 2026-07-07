<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global, reusable attribute catalogue (spec 0017): a typed, dynamic field
 * (STRING/INTEGER/DECIMAL/BOOLEAN/ENUM) assignable to any number of product
 * categories via the `attribute_category` pivot (next migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->string('data_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
