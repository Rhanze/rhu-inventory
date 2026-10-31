<?php

use App\Support\MigrationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSchema::dropForeignKeys('users', 'health_unit_id');
        Schema::table('users', fn (Blueprint $t) => $t->integer('health_unit_id')->nullable()->change());
    }

    public function down(): void {} // Existing PHO accounts require a nullable facility.
};
