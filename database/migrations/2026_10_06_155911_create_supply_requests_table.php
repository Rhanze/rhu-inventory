<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('health_unit_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['health_unit_id', 'status']);
            $table->index(['health_unit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};