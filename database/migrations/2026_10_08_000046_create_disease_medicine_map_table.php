<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disease_medicine_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('disease_id');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedTinyInteger('priority')->default(1);   // 1 = first-line, 2 = second-line
            $table->unsignedInteger('doses_per_case')->default(1); // rough usage per case
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['disease_id', 'medicine_id']);
            $table->index('disease_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disease_medicine_map');
    }
};