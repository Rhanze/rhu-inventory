<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_logs', 'batch_no')) {
                $table->string('batch_no')->nullable()->after('quantity_added');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (Schema::hasColumn('stock_logs', 'batch_no')) {
                $table->dropColumn('batch_no');
            }
        });
    }
};