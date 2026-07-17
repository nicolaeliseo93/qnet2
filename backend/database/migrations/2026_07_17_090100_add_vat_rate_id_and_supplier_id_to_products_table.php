<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds two nullable references to `products`: `vat_rate_id` (the VAT
 * percentage applied to the product) and `supplier_id` (the Registry that
 * supplies it). Both nullOnDelete — a product never restricts removal of its
 * VAT rate or supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vat_rate_id')->nullable()->after('price')->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('vat_rate_id')->constrained('registries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_rate_id');
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
