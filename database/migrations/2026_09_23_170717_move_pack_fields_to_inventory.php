<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add pack columns to inventory
        Schema::table('inventory', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory', 'pack_type')) {
                $table->string('pack_type')->nullable()->after('storage_location');
            }
            if (! Schema::hasColumn('inventory', 'pieces_per_pack')) {
                $table->unsignedInteger('pieces_per_pack')->nullable()->after('pack_type');
            }
        });

        // 2. Backfill from medicines so existing inventory rows get their pack info
        DB::table('inventory')->whereNull('pack_type')->orderBy('id')->each(function ($row) {
            $medicine = DB::table('medicines')->where('id', $row->medicine_id)->first();
            if ($medicine && $medicine->pack_type) {
                DB::table('inventory')->where('id', $row->id)->update([
                    'pack_type' => $medicine->pack_type, 'pieces_per_pack' => $medicine->pieces_per_pack,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            if (Schema::hasColumn('inventory', 'pieces_per_pack')) {
                $table->dropColumn('pieces_per_pack');
            }
            if (Schema::hasColumn('inventory', 'pack_type')) {
                $table->dropColumn('pack_type');
            }
        });
    }
};
