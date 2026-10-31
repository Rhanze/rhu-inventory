<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            // Only add the column if it doesn't already exist
            if (!Schema::hasColumn('allocations', 'destination_health_unit_id')) {
                $table->unsignedBigInteger('destination_health_unit_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (Schema::hasColumn('allocations', 'destination_health_unit_id')) {
                $table->dropColumn('destination_health_unit_id');
            }
        });
    }
};