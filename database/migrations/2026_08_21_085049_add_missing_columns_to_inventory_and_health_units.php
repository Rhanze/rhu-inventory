<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add storage_location to inventory table if it doesn't exist
        if (!Schema::hasColumn('inventory', 'storage_location')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->string('storage_location')->nullable()->after('batch_no');
            });
        }

        // Add facility_type to health_units table if it doesn't exist
        if (!Schema::hasColumn('health_units', 'facility_type')) {
            Schema::table('health_units', function (Blueprint $table) {
                $table->string('facility_type')->nullable()->after('municipality');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn('storage_location');
        });

        Schema::table('health_units', function (Blueprint $table) {
            $table->dropColumn('facility_type');
        });
    }
};