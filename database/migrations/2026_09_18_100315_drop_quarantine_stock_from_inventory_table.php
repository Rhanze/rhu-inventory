<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Safely merge any leftover quarantine stock into available
        // (no data is lost if quarantine_stock > 0 anywhere)
        DB::table('inventory')
            ->where('quarantine_stock', '>', 0)
            ->update([
                'available_stock'  => DB::raw('available_stock + quarantine_stock'),
                'quarantine_stock' => 0,
            ]);

        // Step 2: Drop the column
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn('quarantine_stock');
        });
    }

    public function down(): void
    {
        // Re-create the column if we ever need to rollback
        Schema::table('inventory', function (Blueprint $table) {
            $table->integer('quarantine_stock')->default(0)->after('allocated_stock');
        });
    }
};