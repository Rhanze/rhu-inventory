<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_viewer_states', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('viewer_id');
            $table->timestamp('viewer_read_at')->nullable();
            $table->timestamp('viewer_archived_at')->nullable();
            $table->timestamp('viewer_hidden_at')->nullable();
            $table->primary(['notification_id', 'viewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_viewer_states');
    }
};
