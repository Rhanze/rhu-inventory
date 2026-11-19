<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dispensing_records', 'stock_source')) {
            Schema::table('dispensing_records', fn (Blueprint $table) => $table->string('stock_source', 20)->default('rhu'));
        }
        if (! Schema::hasColumn('allocations', 'dispensing_enabled')) {
            Schema::table('allocations', fn (Blueprint $table) => $table->boolean('dispensing_enabled')->default(false));
        }
        if (! Schema::hasTable('allocation_consumptions')) {
            Schema::create('allocation_consumptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('allocation_id');
                $table->unsignedBigInteger('dispensing_record_id');
                $table->unsignedBigInteger('inventory_id');
                $table->unsignedBigInteger('stock_movement_id')->unique();
                $table->unsignedInteger('quantity');
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['allocation_id', 'inventory_id', 'reversed_at'], 'allocation_consumption_balance_index');
                $table->index('dispensing_record_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_consumptions');
        if (Schema::hasColumn('allocations', 'dispensing_enabled')) {
            Schema::table('allocations', fn (Blueprint $table) => $table->dropColumn('dispensing_enabled'));
        }
        if (Schema::hasColumn('dispensing_records', 'stock_source')) {
            Schema::table('dispensing_records', fn (Blueprint $table) => $table->dropColumn('stock_source'));
        }
    }
};
