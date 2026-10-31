<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->timestamp('outbreak_closed_at')->nullable();
            $table->unsignedBigInteger('outbreak_closed_by')->nullable();
            $table->string('outbreak_closing_note', 500)->nullable();
            $table->unique(['health_unit_id', 'disease_id', 'period_type', 'period_start'], 'disease_report_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropUnique('disease_report_period_unique');
            $table->dropColumn(['period_start', 'period_end', 'is_complete', 'outbreak_closed_at', 'outbreak_closed_by', 'outbreak_closing_note']);
        });
    }
};
