<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispensing_prescription_versions')) {
            return;
        }

        Schema::create('dispensing_prescription_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispensing_record_id')->index();
            $table->string('prescription_image');
            $table->unsignedBigInteger('archived_by');
            $table->timestamp('archived_at');
            $table->string('reason', 20);
        });
    }

    public function down(): void
    {
        // Keep archived clinical evidence if the application code rolls back.
    }
};
