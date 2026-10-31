<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_units', function (Blueprint $table) {
            $table->string('immunization_pattern', 30)
                  ->default('exact')
                  ->after('facility_type');

            $table->tinyInteger('immunization_weekday')
                  ->nullable()
                  ->after('immunization_pattern');

            $table->tinyInteger('immunization_week_number')
                  ->nullable()
                  ->after('immunization_weekday');
        });
    }

    public function down(): void
    {
        Schema::table('health_units', function (Blueprint $table) {
            $table->dropColumn([
                'immunization_pattern',
                'immunization_weekday',
                'immunization_week_number',
            ]);
        });
    }
};