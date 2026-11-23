<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['prenatal_visits', 'postpartum_visits', 'family_planning'] as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $definition) => $definition->softDeletes());
            }
        }
    }

    public function down(): void
    {
        // Preserve void status even if the application code is rolled back.
    }
};
