<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory', 'batch_no')) {
                $table->string('batch_no')->nullable()->after('quarantine_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            if (Schema::hasColumn('inventory', 'batch_no')) {
                $table->dropColumn('batch_no');
            }
        });
    }
};