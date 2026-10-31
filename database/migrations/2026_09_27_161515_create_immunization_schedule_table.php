<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immunization_schedule', function (Blueprint $table) {
            $table->id();
            $table->string('antigen');                 // bcg, hepb, penta, opv, ipv, pcv, mmr, rota
            $table->string('antigen_label');           // "BCG", "Hepatitis B", "Pentavalent"
            $table->unsignedTinyInteger('dose_number'); // 1, 2, 3
            $table->unsignedInteger('min_age_days');   // minimum days after birth to give this dose
            $table->unsignedInteger('max_age_days')->nullable(); // latest acceptable age
            $table->string('recommended_age_label');   // "At birth", "6 weeks", "9 months"
            $table->unsignedInteger('interval_days')->nullable(); // min gap from previous dose
            $table->boolean('is_required_for_fic')->default(false); // counts toward Fully Immunized Child
            $table->boolean('is_required_for_cbi')->default(false); // counts toward Complete Basic Immunization
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['antigen', 'dose_number']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immunization_schedule');
    }
};