<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical Laravel `database` notification channel table.
 *
 * Backs in-app user notifications (see docs/adr/0005-database-notifications.md
 * and docs/api/0004-notifications.md). Each row is one notification delivered to
 * a polymorphic notifiable (currently always a User). `data` holds the
 * serialized payload produced by the notification's toArray(); `read_at` is null
 * while unread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');

            // Polymorphic recipient. Also creates the (notifiable_type,
            // notifiable_id) index used to scope a user's notifications.
            $table->morphs('notifiable');

            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Composite index for the hot read-state queries (unread filter /
            // unread count), scoped per notifiable. Explicitly named to avoid a
            // duplicate index name collision with the one morphs() already made.
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_read_at_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
