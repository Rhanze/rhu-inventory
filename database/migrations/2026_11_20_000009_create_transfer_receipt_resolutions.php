<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_receipt_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_receipt_review_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('stock_log_id')->nullable()->unique();
            $table->unsignedBigInteger('resolved_by');
            $table->string('decision', 20);
            $table->string('reason', 500);
            $table->timestamp('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_receipt_resolutions');
    }
};
