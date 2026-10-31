<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'status')) {
                $table->string('status')->default('pending')->after('destination_health_unit_id');
                $table->index('status');
            }
            if (!Schema::hasColumn('allocations', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('allocations', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (Schema::hasColumn('allocations', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('allocations', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('allocations', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};