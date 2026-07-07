<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic pivot attaching a Tag (spec 0019) to any entity without a
 * schema change. `taggable_id` intentionally has NO db foreign key (morph);
 * `tag_id` gets a `restrictOnDelete` as a db-level backstop BEHIND the
 * service-level delete guard (TagService::delete). No timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');

            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
