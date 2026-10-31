<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // NULL = RHU-shared notification (admin/staff)
            // set  = personal notification, visible only to that user (midwife)
            $table->unsignedBigInteger('user_id')->nullable()->after('health_unit_id');

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_read']);
            $table->dropColumn('user_id');
        });
    }
};