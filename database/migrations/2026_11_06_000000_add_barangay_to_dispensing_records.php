<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dispensing_records', 'barangay')) {
            Schema::table('dispensing_records', function (Blueprint $table) {
                $table->string('barangay', 100)->nullable();
            });
        }
        if (! Schema::hasIndex('dispensing_records', 'dispensing_area_date_index')) {
            Schema::table('dispensing_records', function (Blueprint $table) {
                $table->index(['health_unit_id', 'barangay', 'date_dispensed'], 'dispensing_area_date_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('dispensing_records', 'dispensing_area_date_index')) {
            Schema::table('dispensing_records', fn (Blueprint $table) => $table->dropIndex('dispensing_area_date_index'));
        }
        if (Schema::hasColumn('dispensing_records', 'barangay')) {
            Schema::table('dispensing_records', fn (Blueprint $table) => $table->dropColumn('barangay'));
        }
    }
};
