<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    // 1. Drop old FK if it still exists
    try {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign('fk_activity_logs_health_unit');
        });
    } catch (\Exception $e) {
        // already dropped
    }

    // 2. Make column nullable
    Schema::table('activity_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('health_unit_id')->nullable()->change();
    });
}

public function down(): void
{
    // Reverse: make it non-nullable and re-add the original FK (restrict)
    Schema::table('activity_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('health_unit_id')->nullable(false)->change();
        $table->foreign('health_unit_id')
              ->references('id')
              ->on('health_units')
              ->onDelete('restrict');
    });
    }
};