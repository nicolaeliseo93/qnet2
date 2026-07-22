<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Prodotti di interesse" (user directive 2026-07-22): the products the
 * operator records as interesting for an Opportunity while working the
 * request. A pure many-to-many reference — no quantity, price or note: for
 * now the collection is a control surface ("which products is this request
 * about"), not a quotation.
 *
 * `opportunity_id` cascades (a deleted opportunity drops its own rows);
 * `product_id` is restrictOnDelete, mirroring every other product FK on this
 * codebase (a product referenced by a request cannot silently vanish).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['opportunity_id', 'product_id'], 'opportunity_product_unique_pair');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_product');
    }
};
