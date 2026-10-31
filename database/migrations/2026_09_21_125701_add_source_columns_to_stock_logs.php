<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_logs', 'dispensing_record_id')) {
                $table->unsignedBigInteger('dispensing_record_id')->nullable()->after('added_by');
                $table->index('dispensing_record_id');
            }
            if (!Schema::hasColumn('stock_logs', 'transfer_id')) {
                $table->unsignedBigInteger('transfer_id')->nullable()->after('dispensing_record_id');
                $table->index('transfer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            if (Schema::hasColumn('stock_logs', 'transfer_id')) {
                $table->dropIndex(['transfer_id']);
                $table->dropColumn('transfer_id');
            }
            if (Schema::hasColumn('stock_logs', 'dispensing_record_id')) {
                $table->dropIndex(['dispensing_record_id']);
                $table->dropColumn('dispensing_record_id');
            }
        });
    }
};