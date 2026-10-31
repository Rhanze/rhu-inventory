<?php

use App\Support\MigrationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Drop any FK on allocations.barangay_id (if the FK exists) ──
        $this->dropForeignKeyIfExists('allocations', 'barangay_id');

        if (Schema::hasTable('allocations') && Schema::hasColumn('allocations', 'barangay_id')) {
            Schema::table('allocations', function (Blueprint $table) {
                $table->dropColumn('barangay_id');
            });
        }

        // ── 2. Same treatment for users.barangay_id (if it exists) ──
        $this->dropForeignKeyIfExists('users', 'barangay_id');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'barangay_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('barangay_id');
            });
        }

        // ── 3. Same for public_users.barangay_id (if it exists) ──
        $this->dropForeignKeyIfExists('public_users', 'barangay_id');

        if (Schema::hasTable('public_users') && Schema::hasColumn('public_users', 'barangay_id')) {
            Schema::table('public_users', function (Blueprint $table) {
                $table->dropColumn('barangay_id');
            });
        }

        // ── 4. Drop the barangays table ──
        Schema::dropIfExists('barangays');
    }

    public function down(): void
    {
        if (! Schema::hasTable('barangays')) {
            Schema::create('barangays', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('health_unit_id')->nullable();
                $table->string('barangay_name');
                $table->timestamps();

                $table->index('health_unit_id');
            });
        }
    }

    /**
     * Look up the actual FK name on `{table}.{column}` from information_schema
     * and drop it via raw SQL if found. Silently no-ops if no FK exists.
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        MigrationSchema::dropForeignKeys($table, $column);
    }
};
