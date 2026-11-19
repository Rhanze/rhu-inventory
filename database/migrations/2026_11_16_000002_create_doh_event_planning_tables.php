<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doh_event_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('health_unit_id');
            $table->unsignedBigInteger('doh_program_id');
            $table->date('event_date');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedInteger('quantity')->nullable();
            $table->timestamps();
            $table->unique(['health_unit_id', 'doh_program_id', 'event_date', 'medicine_id'], 'doh_event_material_unique');
        });

        Schema::create('doh_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('dedup_key')->unique();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doh_event_reminders');
        Schema::dropIfExists('doh_event_materials');
    }
};
