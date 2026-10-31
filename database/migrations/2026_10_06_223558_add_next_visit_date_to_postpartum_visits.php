<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postpartum_visits', function (Blueprint $table) {
            $table->date('next_visit_date')->nullable()->after('visit_date');
        });
    }

    public function down(): void
    {
        Schema::table('postpartum_visits', function (Blueprint $table) {
            $table->dropColumn('next_visit_date');
        });
    }
};