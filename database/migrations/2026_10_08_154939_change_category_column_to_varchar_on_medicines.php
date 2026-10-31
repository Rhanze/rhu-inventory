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
        // Convert ENUM → VARCHAR so we can store any program key
        // without a migration every time the DOH adds a new program.
        DB::statement("ALTER TABLE `medicines` MODIFY `category` VARCHAR(64) NOT NULL DEFAULT 'medicine'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Revert back to the original ENUM (destructive if any rows use new keys)
        DB::statement("ALTER TABLE `medicines` MODIFY `category`
            ENUM('medicine','vaccine','family_planning','maternal_child_health','disease_prevention','ncd_lifestyle')
            NOT NULL DEFAULT 'medicine'");
    }
};
