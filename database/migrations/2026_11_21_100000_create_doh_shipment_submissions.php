<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doh_shipment_submissions', function (Blueprint $table) {
            $table->char('token', 64)->primary();
            // Retain the original ID after a shipment reversal to reject stale form replays.
            $table->unsignedBigInteger('shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doh_shipment_submissions');
    }
};
