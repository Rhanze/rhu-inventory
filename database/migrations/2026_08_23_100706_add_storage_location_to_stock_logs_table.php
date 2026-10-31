<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->string('storage_location')->nullable()->after('batch_no');
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropColumn('storage_location');
        });
    }
};