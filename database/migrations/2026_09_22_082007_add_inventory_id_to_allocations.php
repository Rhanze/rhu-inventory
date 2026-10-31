<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'inventory_id')) {
                $table->unsignedBigInteger('inventory_id')->nullable()->after('medicine_id');
                $table->index('inventory_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (Schema::hasColumn('allocations', 'inventory_id')) {
                $table->dropIndex(['inventory_id']);
                $table->dropColumn('inventory_id');
            }
        });
    }
};