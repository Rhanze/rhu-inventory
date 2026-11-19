<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_import_batches', function (Blueprint $table) {
            $table->uuid('token')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('section', 60);
            $table->char('file_hash', 64);
            $table->unsignedInteger('row_count');
            $table->timestamp('imported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_import_batches');
    }
};
