<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            // true  = given to citizens by the RHU (searchable / requestable)
            // false = internal supply only (PPE, equipment, manuals, lab kits)
            $table->boolean('is_dispensable')
                  ->default(true)
                  ->after('requires_prescription');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('is_dispensable');
        });
    }
};