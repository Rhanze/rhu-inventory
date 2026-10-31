<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the destination column (free-text) after health_unit_id
        if (!Schema::hasColumn('allocations', 'destination')) {
            Schema::table('allocations', function (Blueprint $table) {
                $table->string('destination', 255)->nullable()->after('health_unit_id');
            });
        }

        // Backfill: if barangay_id still exists, copy barangay names into destination
        if (Schema::hasTable('barangays') && Schema::hasColumn('allocations', 'barangay_id')) {
            DB::table('allocations')
                ->whereNotNull('barangay_id')
                ->orderBy('id')
                ->each(function ($row) {
                    $barangayName = DB::table('barangays')
                        ->where('id', $row->barangay_id)
                        ->value('barangay_name');

                    if ($barangayName) {
                        DB::table('allocations')
                            ->where('id', $row->id)
                            ->update(['destination' => $barangayName]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('allocations', 'destination')) {  
            Schema::table('allocations', function (Blueprint $table) {
                $table->dropColumn('destination');
            });
        }
    }
};