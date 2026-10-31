<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('health_unit_id');   // plain column, no foreign key
            $table->string('philhealth_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address');
            $table->string('contact_number')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();     // optional, for tracking when created/updated
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_users');
    }
};