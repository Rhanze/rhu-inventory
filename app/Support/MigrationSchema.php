<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class MigrationSchema
{
    public static function dropForeignKeys(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach (Schema::getForeignKeys($table) as $key) {
            if (in_array($column, $key['columns'], true)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign($key['name']));
            }
        }
    }
}
