<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('medicines', 'unit')) {
            Schema::table('medicines', fn (Blueprint $t) => $t->string('unit', 50)->nullable());
            DB::table('medicines')->update(['unit' => DB::raw('base_unit')]);
        }
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        }
        Schema::table('public_users', function (Blueprint $t) {
            foreach (['household_id', 'family_serial_no', 'barangay', 'risk_codes'] as $name) {
                if (! Schema::hasColumn('public_users', $name)) {
                    $t->string($name, $name === 'risk_codes' ? 500 : 100)->nullable();
                }
            }
            if (! Schema::hasColumn('public_users', 'mother_id')) {
                $t->unsignedBigInteger('mother_id')->nullable();
            }
            if (! Schema::hasColumn('public_users', 'is_pregnant')) {
                $t->boolean('is_pregnant')->default(false);
            }
            if (! Schema::hasColumn('public_users', 'lmp')) {
                $t->date('lmp')->nullable();
            }
            foreach (['gravida', 'para'] as $name) {
                if (! Schema::hasColumn('public_users', $name)) {
                    $t->integer($name)->nullable();
                }
            }
        });
        Schema::table('public_users', fn (Blueprint $t) => $t->string('philhealth_id')->nullable()->change());
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('transfer_requests', fn (Blueprint $t) => $t->string('status')->default('pending')->change());
        }
        Schema::table('transfer_requests', function (Blueprint $t) {
            if (! Schema::hasColumn('transfer_requests', 'inventory_id')) {
                $t->unsignedBigInteger('inventory_id')->nullable();
            }
            foreach (['batch_no', 'dosage', 'dosage_form', 'category', 'pack_type'] as $name) {
                if (! Schema::hasColumn('transfer_requests', $name)) {
                    $t->string($name)->nullable();
                }
            }
            if (! Schema::hasColumn('transfer_requests', 'pieces_per_pack')) {
                $t->unsignedInteger('pieces_per_pack')->nullable();
            }
        });
    }

    public function down(): void {} // Preserve pre-existing legacy columns.
};
