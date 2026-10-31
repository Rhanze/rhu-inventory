<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doh_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#42ad8a');
            $table->string('recurrence', 20);                    // yearly | monthly | weekly
            $table->unsignedTinyInteger('month')->nullable();    // 1-12
            $table->unsignedTinyInteger('day')->nullable();      // 1-31
            $table->unsignedTinyInteger('weekday')->nullable();  // 0-6 (Sun=0)
            $table->unsignedTinyInteger('duration_days')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doh_programs');
    }
};