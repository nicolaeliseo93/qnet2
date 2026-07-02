<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable contact module.
 *
 * A Contact is a single reachable channel (a phone number, an email, a website,
 * ...) attached to any owning entity through a nullable polymorphic relation
 * (contactable_type / contactable_id), so future models can own many contacts
 * via morphMany (HasContacts) without a schema change. Nullable so a contact
 * can also exist standalone before being linked.
 *
 * `is_primary` marks the preferred channel of its type for an owner; the
 * "at most one primary per owner+type" invariant is enforced in ContactService,
 * not by a DB constraint (the morph relation has no DB-level FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: any model can own contacts via morphMany.
            // Nullable so a contact may exist before being attached.
            $table->nullableMorphs('contactable');

            // phone | mobile | email | pec | website | ... (ContactTypeEnum).
            $table->string('type');

            // Optional human label to distinguish multiple contacts of one
            // owner (e.g. "Work", "Home", "Support").
            $table->string('label')->nullable();

            // The actual channel value (number, address, url). Validated
            // per-type at the HTTP boundary.
            $table->string('value');

            // Preferred channel of its type for the owner.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
