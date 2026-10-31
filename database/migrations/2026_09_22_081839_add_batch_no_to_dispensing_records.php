<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensing_records', function (Blueprint $table) {
            if (!Schema::hasColumn('dispensing_records', 'batch_no')) {
                $table->string('batch_no')->nullable()->after('quantity_dispensed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispensing_records', function (Blueprint $table) {
            if (Schema::hasColumn('dispensing_records', 'batch_no')) {
                $table->dropColumn('batch_no');
            }
        });
    }
};