<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('midwife_barangay')) {
            return;
        }

        // Imported legacy tables can contain only an ID and timestamps. Preserve
        // those rows without inventing a midwife or barangay assignment for them.
        if (! Schema::hasColumn('midwife_barangay', 'user_id')) {
            Schema::table('midwife_barangay', fn (Blueprint $table) => $table->integer('user_id')->nullable());
        }
        if (! Schema::hasColumn('midwife_barangay', 'barangay')) {
            Schema::table('midwife_barangay', fn (Blueprint $table) => $table->string('barangay', 100)->nullable());
        }
        if (! Schema::hasColumn('midwife_barangay', 'is_primary')) {
            Schema::table('midwife_barangay', fn (Blueprint $table) => $table->boolean('is_primary')->default(false));
        }
    }

    public function down(): void {} // Retain existing assignments when rolling back.
};
