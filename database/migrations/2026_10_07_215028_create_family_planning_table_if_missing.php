<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If the table already exists in prod, do nothing.
        if (Schema::hasTable('family_planning')) {
            return;
        }

        Schema::create('family_planning', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('health_unit_id');
            $table->string('method', 50);
            $table->date('date_accepted');
            $table->date('next_supply_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('patient_id');
            $table->index('date_accepted');
            $table->index(['health_unit_id', 'next_supply_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_planning');
    }
};