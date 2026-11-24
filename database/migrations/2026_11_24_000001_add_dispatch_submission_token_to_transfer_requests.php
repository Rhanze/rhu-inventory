<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transfer_requests', 'dispatch_submission_token')) {
            Schema::table('transfer_requests', function (Blueprint $table) {
                $table->char('dispatch_submission_token', 64)->nullable();
            });
        }

        if (! Schema::hasIndex('transfer_requests', ['dispatch_submission_token'], 'unique')) {
            Schema::table('transfer_requests', function (Blueprint $table) {
                $table->unique('dispatch_submission_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropUnique(['dispatch_submission_token']);
            $table->dropColumn('dispatch_submission_token');
        });
    }
};
