<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if the column already exists (idempotent — safe to re-run).
        if (Schema::hasColumn('public_users', 'assigned_midwife_id')) {
            return;
        }

        Schema::table('public_users', function (Blueprint $table) {
            // foreignId()->constrained('users') auto-detects users.id's
            // actual type (int vs bigint, signed vs unsigned) — this is
            // what fixes the "errno 150 Foreign key constraint is
            // incorrectly formed" error.
            $table->foreignId('assigned_midwife_id')
                  ->nullable()
                  ->after('registered_by')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->index('assigned_midwife_id');
        });

        // Backfill: preserve current behaviour for existing rows.
        DB::table('public_users')
            ->whereNull('assigned_midwife_id')
            ->whereNotNull('registered_by')
            ->update(['assigned_midwife_id' => DB::raw('registered_by')]);
    }

    public function down(): void
    {
        Schema::table('public_users', function (Blueprint $table) {
            // Drop FK + index + column in the correct order.
            $table->dropForeign(['assigned_midwife_id']);
            $table->dropIndex(['assigned_midwife_id']);
            $table->dropColumn('assigned_midwife_id');
        });
    }
};