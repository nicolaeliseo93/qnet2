<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request Management module (spec 0052, D-1): the next-callback planning
 * field on an Opportunity. `next_callback_at` is the operator-editable
 * date-and-time to call the customer back, indexed because the table
 * (RequestManagementTableDefinition) sorts/filters on it. Its sibling
 * `next_callback_reminded_at` is a plain marker column reserved for a future
 * reminder job (D-1/D-4): NOT exposed by any API and never written in this
 * phase. Both stay OUTSIDE Opportunity's #[Fillable] (D-2) — written
 * exclusively by RequestManagementService::updateWork().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dateTime('next_callback_at')->nullable()->after('attribute_values')->index();
            $table->dateTime('next_callback_reminded_at')->nullable()->after('next_callback_at');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['next_callback_at']);
            $table->dropColumn(['next_callback_at', 'next_callback_reminded_at']);
        });
    }
};
