<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immunization_records', function (Blueprint $table) {
            $table->id();

            // public_users.id = bigint → foreignId() is correct
            $table->foreignId('public_user_id')
                  ->constrained('public_users')
                  ->cascadeOnDelete();

            // health_units.id = int(11) SIGNED → must use integer(), NOT unsignedInteger()
            $table->integer('health_unit_id');
            $table->foreign('health_unit_id')
                  ->references('id')->on('health_units');

            $table->string('antigen');
            $table->unsignedTinyInteger('dose_number');
            $table->date('date_given');

            // inventory table — no FK, just store the id
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->index('inventory_id');

            $table->string('batch_no')->nullable();
            $table->unsignedBigInteger('administered_by')->nullable();
            $table->string('site')->nullable();      // "Left thigh", "Right deltoid"
            $table->string('route')->nullable();     // "Intradermal", "Intramuscular", "Subcutaneous", "Oral"
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['public_user_id', 'antigen', 'dose_number'], 'uq_child_antigen_dose');
            $table->index(['health_unit_id', 'date_given']);
            $table->index(['health_unit_id', 'antigen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immunization_records');
    }
};