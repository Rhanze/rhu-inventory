<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_chain_logs', function (Blueprint $table) {
            $table->id();

            // health_units.id = int(11) SIGNED → must use integer(), NOT foreignId()
            $table->integer('health_unit_id');
            $table->foreign('health_unit_id')
                  ->references('id')->on('health_units');

            $table->dateTime('logged_at');
            $table->decimal('temperature_c', 5, 2);        // e.g. 4.50
            $table->string('equipment')->default('Refrigerator');
            $table->string('vvm_status')->nullable();      // "Stage 1", "Stage 2", "Stage 3", "Stage 4"
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('logged_by')->nullable();
            $table->timestamps();

            $table->index(['health_unit_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_chain_logs');
    }
};