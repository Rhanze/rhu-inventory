<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MMSL Fallback Thresholds
    |--------------------------------------------------------------------------
    | Used when a medicine has no explicit min_stock / max_stock value set.
    | These act as province-wide defaults until per-medicine values are entered.
    */

    'default_min_stock' => env('INVENTORY_DEFAULT_MIN_STOCK', 50),
    'default_max_stock' => env('INVENTORY_DEFAULT_MAX_STOCK', 500),
];