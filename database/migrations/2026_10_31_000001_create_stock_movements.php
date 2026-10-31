<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 50);
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('inventory_id');
            $table->integer('available_delta');
            $table->integer('allocated_delta')->default(0);
            $table->unsignedBigInteger('reverses_id')->nullable()->unique();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['owner_type', 'owner_id']);
        });
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_id')->nullable()->index();
            $table->string('movement_type', 30)->nullable()->index();
            $table->timestamp('voided_at')->nullable();
        });
        Schema::table('transfer_requests', fn (Blueprint $table) => $table->boolean('stock_reserved')->default(false));
    }

    public function down(): void
    {
        Schema::table('transfer_requests', fn (Blueprint $table) => $table->dropColumn('stock_reserved'));
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropIndex(['inventory_id']);
            $table->dropIndex(['movement_type']);
            $table->dropColumn(['inventory_id', 'movement_type', 'voided_at']);
        });
        Schema::dropIfExists('stock_movements');
    }
};
