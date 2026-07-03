<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User employment profile (spec 0015): a one-to-one extension of `users`
 * covering the Profile / Contractual relationship / Contractual data
 * sections gathered in the redesigned user form. `user_id` is unique so the
 * relation is truly hasOne; every other FK nulls the row's own column on the
 * related record's delete (a manager/function/company/site disappearing
 * never cascades to deleting the employment row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('is_manager')->default(false);
            $table->string('job_description')->nullable();
            $table->foreignId('reports_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_function_id')->nullable()->constrained()->nullOnDelete();
            $table->string('relationship_type')->nullable();

            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operational_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('qualification_type')->nullable();
            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();

            $table->unsignedSmallInteger('standard_daily_minutes')->nullable();
            $table->unsignedSmallInteger('break_daily_minutes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_profiles');
    }
};
