<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Diseases masterlist ───
        Schema::create('diseases', function (Blueprint $table) {
            $table->id();                                    // bigint unsigned
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_reportable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─── Weekly/monthly disease case reports ───
        Schema::create('disease_reports', function (Blueprint $table) {
            $table->id();

            // diseases.id is bigint unsigned (from ->id()) → foreignId matches
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnDelete();

            // health_units.id is int(11) SIGNED → must use signed integer
            $table->integer('health_unit_id');
            $table->foreign('health_unit_id')->references('id')->on('health_units')->cascadeOnDelete();

            $table->date('report_date');
            $table->enum('period_type', ['weekly', 'monthly'])->default('weekly');
            $table->integer('case_count')->default(0);
            $table->boolean('is_outbreak')->default(false);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamps();

            $table->index(['health_unit_id', 'report_date']);
            $table->index(['disease_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disease_reports');
        Schema::dropIfExists('diseases');
    }
};