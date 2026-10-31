<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('health_unit_id')->nullable();
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('type'); // 'low_stock', 'near_expiry', 'expired'
            $table->string('title');
            $table->text('message');
            $table->string('severity')->default('warning'); // 'info', 'warning', 'danger'
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for performance
            $table->index('health_unit_id');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};