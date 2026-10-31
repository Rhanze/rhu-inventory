<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Family Planning Method Intervals
    |--------------------------------------------------------------------------
    |
    | How many weeks until the patient should return for resupply / follow-up.
    | The key is a lowercase substring matched against the `method` column.
    | First match wins — order matters (put more specific patterns first).
    |
    | null = no automatic follow-up (method is permanent or self-managed).
    |
    */

    'intervals' => [
        // Order matters — most specific first
        'implant'                 => 156,   // 3 years
        'iud'                     => 52,    // annual check after insertion
        'dmpa'                    => 12,    // Depo-Provera injectable — 3 months
        'depo'                    => 12,
        'injectable'              => 12,
        'inject'                  => 12,
        'coc'                     => 4,     // Combined oral contraceptive
        'combined oral'           => 4,
        'pop'                     => 4,     // Progestin-only pill
        'progestin'               => 4,
        'pill'                    => 4,
        'condom'                  => 4,
        'lam'                     => 24,    // Lactational amenorrhea — 6 months
        'btl'                     => null,  // Bilateral tubal ligation — permanent
        'nsv'                     => null,  // No-scalpel vasectomy — permanent
        'vasectomy'               => null,
        'tubal'                   => null,
        'permanent'               => null,
        'natural'                 => null,  // NFP — no resupply
        'rhythm'                  => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default fallback interval
    |--------------------------------------------------------------------------
    |
    | Used when a method isn't in the list above. Set to null to leave
    | next_supply_date empty so the midwife fills it in manually.
    |
    */
    'default_interval' => null,

];