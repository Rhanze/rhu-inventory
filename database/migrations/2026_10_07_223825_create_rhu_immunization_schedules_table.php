<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rhu_immunization_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('health_unit_id');
            $table->string('area_label', 120);
            $table->string('pattern', 30);
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['health_unit_id', 'is_active']);
            $table->unique(['health_unit_id', 'area_label'], 'rhu_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rhu_immunization_schedules');
    }
};