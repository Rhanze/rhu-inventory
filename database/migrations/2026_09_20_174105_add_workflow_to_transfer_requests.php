<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand the status enum + convert legacy "approved" to "accepted"
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transfer_requests
            MODIFY COLUMN status ENUM('pending','accepted','approved','rejected','received','completed','cancelled')
            DEFAULT 'pending'");
        }

        DB::table('transfer_requests')->where('status', 'approved')->update(['status' => 'accepted']);

        // 2. Add the new columns — types match inventory.id (INT UNSIGNED)
        Schema::table('transfer_requests', function (Blueprint $table) {
            // Matches inventory.id: int(11) signed
            $table->integer('inventory_id')->nullable()->after('medicine_id');

            // Snapshot of medicine details
            $table->string('batch_no')->nullable()->after('inventory_id');
            $table->string('dosage')->nullable()->after('batch_no');
            $table->string('dosage_form')->nullable()->after('dosage');
            $table->string('category')->nullable()->after('dosage_form');
            $table->string('pack_type')->nullable()->after('category');
            $table->unsignedInteger('pieces_per_pack')->nullable()->after('pack_type');

            // Receive workflow
            $table->timestamp('received_at')->nullable()->after('completed_at');
            $table->integer('received_by')->nullable()->after('received_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('received_by');
            $table->boolean('auto_completed')->default(false)->after('reminder_sent_at');

            $table->index('inventory_id');
            $table->index('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropIndex(['inventory_id']);
            $table->dropIndex(['received_by']);
            $table->dropColumn([
                'inventory_id',
                'batch_no',
                'dosage',
                'dosage_form',
                'category',
                'pack_type',
                'pieces_per_pack',
                'received_at',
                'received_by',
                'reminder_sent_at',
                'auto_completed',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transfer_requests
            MODIFY COLUMN status ENUM('pending','approved','rejected','completed','cancelled')
            DEFAULT 'pending'");
        }
    }
};
