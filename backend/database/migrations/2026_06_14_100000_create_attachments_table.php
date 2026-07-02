<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable, polymorphic file-attachment module.
 *
 * Designed as a drop-in component: a file is attached to any owning entity
 * through a nullable polymorphic relation (attachable_type / attachable_id), so
 * any current or future model (users, companies, documents, ...) can own one or
 * many attachments without a schema change. The relation is nullable so a file
 * can also be uploaded standalone before being linked to an entity.
 *
 * The binary lives on a filesystem disk (default: the private local disk); only
 * its metadata is persisted here. `disk` + `path` locate the stored object so
 * the same table can transparently span local and cloud storage later without a
 * migration. No file is ever served statically: access goes through an
 * authenticated, authorized download endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: any model can own attachments via morphMany/morphOne.
            // Nullable so a file may exist before being attached to an entity.
            $table->nullableMorphs('attachable');

            // Optional logical grouping of an owner's files (e.g. "avatar",
            // "documents", "invoices"), analogous to a media collection.
            $table->string('collection')->nullable()->index();

            // Where the binary is stored. `disk` is a filesystems.php disk name;
            // `path` is the key within that disk. Unique together: one row owns
            // one stored object.
            $table->string('disk')->default('local');
            $table->string('path');

            // Client-supplied original file name, kept for display and download.
            $table->string('original_name');

            // Detected MIME type and extension, plus size in bytes.
            $table->string('mime_type');
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size');

            // Who uploaded the file. Nullable + nullOnDelete: deleting the
            // uploader must not delete their uploaded files.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
