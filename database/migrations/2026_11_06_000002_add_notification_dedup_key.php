<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notifications', 'dedup_key')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('dedup_key')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notifications', 'dedup_key')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex(['dedup_key']);
                $table->dropColumn('dedup_key');
            });
        }
    }
};
