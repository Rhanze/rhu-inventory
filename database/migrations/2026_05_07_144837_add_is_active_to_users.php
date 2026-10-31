<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_active'));
        }
    }
};
