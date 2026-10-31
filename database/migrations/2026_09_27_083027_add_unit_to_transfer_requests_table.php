<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('transfer_requests', 'unit')) {
                $table->string('unit')->nullable()->after('dosage_form');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_requests', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};