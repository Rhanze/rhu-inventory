<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doh_shipments', fn (Blueprint $table) => $table->char('delivery_key', 64)->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('doh_shipments', function (Blueprint $table) {
            $table->dropUnique(['delivery_key']);
            $table->dropColumn('delivery_key');
        });
    }
};
