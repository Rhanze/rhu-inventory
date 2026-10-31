<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('area_label', 120)->nullable()->after('priority');
            $table->index(['health_unit_id', 'area_label', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['health_unit_id', 'area_label', 'event_date']);
            $table->dropColumn('area_label');
        });
    }
};