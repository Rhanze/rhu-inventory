<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (!Schema::hasColumn('medicines', 'dosage_form')) {
                $table->string('dosage_form')->nullable()->after('category');
            }
            if (!Schema::hasColumn('medicines', 'base_unit')) {
                $table->string('base_unit')->nullable()->after('dosage_form');
            }
            if (!Schema::hasColumn('medicines', 'pack_type')) {
                $table->string('pack_type')->nullable()->after('base_unit');
            }
            if (!Schema::hasColumn('medicines', 'pieces_per_pack')) {
                $table->integer('pieces_per_pack')->nullable()->after('pack_type');
            }
        });
    }
    
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['dosage_form', 'base_unit', 'pack_type', 'pieces_per_pack']);
        });
    }
};