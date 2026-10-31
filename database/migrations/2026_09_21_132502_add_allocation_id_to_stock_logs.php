<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_logs', 'allocation_id')) {
                $table->unsignedBigInteger('allocation_id')->nullable()->after('transfer_id');
                $table->index('allocation_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (Schema::hasColumn('stock_logs', 'allocation_id')) {
                $table->dropIndex(['allocation_id']);
                $table->dropColumn('allocation_id');
            }
        });
    }
};