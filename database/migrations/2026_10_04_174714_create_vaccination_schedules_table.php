<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('health_unit_id');
            $table->unsignedBigInteger('public_user_id');
            $table->string('vaccine_name', 50);
            $table->unsignedTinyInteger('dose_number')->default(1);
            $table->date('due_date');
            $table->date('given_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending | given | missed | skipped
            $table->unsignedBigInteger('immunization_record_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['health_unit_id', 'due_date']);
            $table->index(['public_user_id', 'status']);
            $table->index(['health_unit_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_schedules');
    }
};