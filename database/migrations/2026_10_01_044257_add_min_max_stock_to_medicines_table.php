<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (!Schema::hasColumn('medicines', 'min_stock')) {
                $table->integer('min_stock')->nullable()->after('requires_prescription')
                    ->comment('MMSL: minimum stock (reorder point). Null = use system default.');
            }
            if (!Schema::hasColumn('medicines', 'max_stock')) {
                $table->integer('max_stock')->nullable()->after('min_stock')
                    ->comment('MMSL: maximum stock (overstock threshold). Null = use system default.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};