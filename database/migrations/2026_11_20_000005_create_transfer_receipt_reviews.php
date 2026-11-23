<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transfer_receipt_reviews')) {
            return;
        }

        Schema::create('transfer_receipt_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_request_id')->unique()->constrained('transfer_requests')->restrictOnDelete();
            $table->unsignedBigInteger('reviewed_by');
            $table->unsignedInteger('expected_quantity');
            $table->unsignedInteger('received_quantity');
            $table->string('evidence_reference', 120);
            $table->timestamp('reviewed_at');
        });
    }

    public function down(): void
    {
        // Receipt evidence remains available if the application code is rolled back.
    }
};
