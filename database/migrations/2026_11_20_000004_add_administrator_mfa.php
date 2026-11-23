<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'mfa_secret')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('mfa_secret')->nullable());
        }
        if (! Schema::hasColumn('users', 'mfa_last_step')) {
            Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('mfa_last_step')->nullable());
        }
    }

    public function down(): void
    {
        // Retain enrolled authenticators and their replay counters on rollback.
    }
};
