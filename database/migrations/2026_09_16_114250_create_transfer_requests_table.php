<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->enum('initiator_type', ['rhu_request', 'pho_directive'])->default('rhu_request');
            $table->unsignedBigInteger('from_health_unit_id');  // Source (has stock)
            $table->unsignedBigInteger('to_health_unit_id');    // Destination (needs stock)
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'accepted', 'approved', 'rejected', 'received', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['from_health_unit_id', 'status']);
            $table->index(['to_health_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
