<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->date('reserved_expiration_date')->nullable();
            $table->boolean('has_batch_snapshot')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requests', fn (Blueprint $table) => $table->dropColumn(['reserved_expiration_date', 'has_batch_snapshot']));
    }
};
