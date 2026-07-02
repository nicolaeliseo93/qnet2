<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a city to its province (the level between state/region and city).
     * Nullable: many countries have no province level, and those cities stay
     * reachable through state_id / country_id. cascadeOnDelete mirrors the
     * existing country_id / state_id foreign keys (reference data).
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('province_id')->nullable()->after('state_id');

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->onDelete('cascade');

            $table->index('province_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropIndex(['province_id']);
            $table->dropColumn('province_id');
        });
    }
};
