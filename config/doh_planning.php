<?php

return [
    'horizon_days' => 30,
    'reminder_days' => [14, 7],

    // Catalog candidates for logistics review. Quantities come from local history or the RHU plan.
    'materials' => [
        'immunization' => [
            'categories' => ['vaccine'],
            'names' => ['syringe', 'safety box'],
            'source' => 'https://doh.gov.ph/wp-content/uploads/2023/08/Booklet-10-VACCINES-COLD-CHAIN-AND-LOGISTICS-MANAGEMENT.pdf',
        ],
        'family planning' => [
            'categories' => ['family_planning'],
            'names' => [],
            'source' => 'https://ro11.doh.gov.ph/family-planning-program/',
        ],
        'deworming' => [
            'categories' => [],
            'names' => ['albendazole', 'mebendazole'],
            'source' => 'https://ro11.doh.gov.ph/doh-urges-public-to-support-july-deworming-program-warns-against-misinformation/',
        ],
        'garantisadong pambata' => [
            'categories' => [],
            'names' => ['vitamin a', 'ferrous', 'iron', 'albendazole', 'mebendazole'],
            'source' => 'https://ro1.doh.gov.ph/garantisadong-pambata/',
        ],
        'tb day' => [
            'categories' => [],
            'names' => ['sputum', 'xpert'],
            'source' => 'https://itis2.doh.gov.ph/assets/img/downloads/mop/NTP_MOP_6th_Edition.pdf',
        ],
        'aids' => [
            'categories' => [],
            'names' => ['condom', 'hiv'],
            'source' => 'https://www.who.int/news-room/questions-and-answers/item/hiv-aids',
        ],
        'diabetes' => [
            'categories' => [],
            'names' => ['glucometer', 'glucose test', 'lancet'],
            'source' => 'https://extranet.who.int/ncdccs/Data/PHL_D1_PHIL%20PEN.pdf',
        ],
        'handwashing' => [
            'categories' => [],
            'names' => ['soap', 'alcohol', 'hand sanitizer'],
            'source' => 'https://www.deped.gov.ph/2021/10/15/deped-doh-commit-to-create-culture-of-handwashing-in-schools-and-communities-amid-covid-19-pandemic/',
        ],
        'maternal' => [
            'categories' => [],
            'names' => ['ferrous', 'folic acid', 'iron'],
            'source' => 'https://ro7.doh.gov.ph/pmnp',
        ],
        'folic acid' => [
            'categories' => [],
            'names' => ['folic acid'],
            'source' => 'https://nnc.gov.ph/luzon-region/prevent-folic-acid-deficiency-for-a-safe-healthy-pregnancy/',
        ],
        'nutrition' => [
            'categories' => ['family_health_nutrition'],
            'names' => [],
            'source' => 'https://ro7.doh.gov.ph/pmnp',
        ],
    ],
];
