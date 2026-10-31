<?php

use App\Support\MigrationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationSchema::dropForeignKeys('allocations', 'barangay_id');
        Schema::table('allocations', fn (Blueprint $t) => $t->unsignedBigInteger('barangay_id')->nullable()->change());
    }

    public function down(): void {} // Do not reintroduce a discarded legacy constraint.
};
