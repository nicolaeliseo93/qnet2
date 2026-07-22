<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Login as customer" impersonation (spec 0050, D-1): a dedicated Sanctum
 * token is issued for the TARGET user when an actor starts an impersonation
 * session, tagged with `impersonated_by` = the ORIGINAL actor's id. Nullable
 * (every ordinary token has no value here), guarded (not in
 * PersonalAccessToken::$fillable), so it is only ever set via forceFill() by
 * ImpersonationService — never mass-assignable through the token's own
 * create() call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('impersonated_by')->nullable()->after('expires_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impersonated_by');
        });
    }
};
