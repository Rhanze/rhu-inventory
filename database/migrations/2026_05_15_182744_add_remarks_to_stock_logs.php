<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_logs', 'remarks')) {
                $table->string('remarks')->nullable()->after('added_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (Schema::hasColumn('stock_logs', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });
    }
};