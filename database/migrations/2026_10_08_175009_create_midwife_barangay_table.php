<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('midwife_barangay', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('barangay', 100);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'barangay']);
            $table->index('barangay');
            $table->index(['barangay', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('midwife_barangay');
    }
};