<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_users', function (Blueprint $table) {
            $table->unsignedBigInteger('registered_by')->nullable()->after('health_unit_id');
            $table->index('registered_by');
        });

        // Backfill existing patients with the first admin/staff user at each RHU,
        // so they don't disappear from every midwife's view after this change.
        DB::table('public_users')
            ->whereNull('registered_by')
            ->orderBy('id')
            ->each(function ($patient) {
                $fallbackUserId = DB::table('users')
                    ->where('health_unit_id', $patient->health_unit_id)
                    ->whereIn('role', ['admin', 'staff'])
                    ->orderBy('id')
                    ->value('id');

                if ($fallbackUserId) {
                    DB::table('public_users')
                        ->where('id', $patient->id)
                        ->update(['registered_by' => $fallbackUserId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('public_users', function (Blueprint $table) {
            $table->dropIndex(['registered_by']);
            $table->dropColumn('registered_by');
        });
    }
};