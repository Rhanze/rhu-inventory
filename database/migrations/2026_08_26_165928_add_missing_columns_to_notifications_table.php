<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add columns if they don't exist
            if (! Schema::hasColumn('notifications', 'inventory_id')) {
                $table->unsignedBigInteger('inventory_id')->nullable()->after('health_unit_id');
            }
            if (! Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->after('inventory_id');
            }
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->after('type');
            }
            if (! Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->after('title');
            }
            if (! Schema::hasColumn('notifications', 'severity')) {
                $table->string('severity')->default('warning')->after('message');
            }
            if (! Schema::hasColumn('notifications', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('severity');
            }
            if (! Schema::hasColumn('notifications', 'created_at')) {
                $table->timestamp('created_at')->useCurrent()->after('is_read');
            }

            // Add indexes for performance
            if (! Schema::hasIndex('notifications', ['inventory_id'])) {
                $table->index('inventory_id');
            }
            if (! Schema::hasIndex('notifications', ['is_read'])) {
                $table->index('is_read');
            }
            if (! Schema::hasIndex('notifications', ['created_at'])) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_id',
                'type',
                'title',
                'message',
                'severity',
                'is_read',
                'created_at',
            ]);
        });
    }
};
