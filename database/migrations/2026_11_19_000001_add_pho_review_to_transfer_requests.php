<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->string('pho_review_status', 16)->nullable();
            $table->unsignedBigInteger('pho_reviewed_by')->nullable();
            $table->timestamp('pho_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropColumn(['pho_review_status', 'pho_reviewed_by', 'pho_reviewed_at']);
        });
    }
};
