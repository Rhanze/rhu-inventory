<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensing_records', function (Blueprint $table) {
            if (!Schema::hasColumn('dispensing_records', 'prescription_image')) {
                $table->string('prescription_image')->nullable()->after('doctor_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispensing_records', function (Blueprint $table) {
            if (Schema::hasColumn('dispensing_records', 'prescription_image')) {
                $table->dropColumn('prescription_image');
            }
        });
    }
};