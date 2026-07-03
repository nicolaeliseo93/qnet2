<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-role field-permission matrix (spec 0006): an administrator restricts,
 * per role and per resource field, whether the field is visible/editable/
 * required. This is the DB layer consulted by
 * AbstractResourceAuthorization::fieldPermissions() — it can only RESTRICT
 * within the code-defined ceiling (fieldPermissionCeiling()), never escalate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_field_permissions', function (Blueprint $table) {
            $table->id();

            // Cascade: a role's matrix rows are meaningless once the role is gone.
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            // The ResourceAuthorization::resource() key (e.g. "users") and one of
            // its FieldDefinition keys — validated at the HTTP boundary against
            // the AuthorizationRegistry (never trusted raw; see
            // ValidatesFieldPermissionsMatrix).
            $table->string('resource');
            $table->string('field');

            $table->boolean('visible')->default(true);
            $table->boolean('editable')->default(true);
            $table->boolean('required')->default(false);

            $table->timestamps();

            // One row per role per resource field — the write is a full-replace
            // sync (RoleService), the read is a single indexed lookup per role set.
            $table->unique(['role_id', 'resource', 'field']);
            $table->index(['resource', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_field_permissions');
    }
};
