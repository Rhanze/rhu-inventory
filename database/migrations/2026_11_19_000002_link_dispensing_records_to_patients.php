<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensing_records', function (Blueprint $table) {
            $table->foreignId('public_user_id')->nullable()->index()->constrained('public_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispensing_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('public_user_id');
        });
    }
};
