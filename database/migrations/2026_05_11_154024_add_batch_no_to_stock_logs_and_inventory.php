<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->string('batch_no')->nullable()->after('quantity_added');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->string('batch_no')->nullable()->after('quarantine_stock');
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropColumn('batch_no');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn('batch_no');
        });
    }
};