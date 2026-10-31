<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Expand the enum to include 'midwife'
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'staff', 'midwife')
            NOT NULL DEFAULT 'staff'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'staff')
            NOT NULL DEFAULT 'staff'
        ");
    }
};
