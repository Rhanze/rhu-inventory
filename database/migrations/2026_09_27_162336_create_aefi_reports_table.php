<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aefi_reports', function (Blueprint $table) {
            $table->id();

            // public_users.id = bigint → foreignId() is correct
            $table->foreignId('public_user_id')
                  ->constrained('public_users')
                  ->cascadeOnDelete();

            // health_units.id = int(11) SIGNED → must use integer()
            $table->integer('health_unit_id');
            $table->foreign('health_unit_id')
                  ->references('id')->on('health_units');

            // immunization_records.id = bigint → foreignId() is correct
            $table->foreignId('immunization_record_id')
                  ->nullable()
                  ->constrained('immunization_records')
                  ->nullOnDelete();

            $table->string('antigen')->nullable();
            $table->date('date_onset');
            $table->text('symptoms');
            $table->string('severity')->default('minor');
            $table->date('reported_at');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->text('outcome')->nullable();
            $table->timestamps();

            $table->index(['health_unit_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aefi_reports');
    }
};