<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data Sync Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Define the mapping of (category -> status) to Google Sheets name
    | and Webflow Collection ID. This allows dynamic mapping.
    |
    */

    'dark_pool' => [
        'spreadsheet_id' => env('GOOGLE_SHEET_ID_DARK_POOL'),
        'main_tab' => 'DARK POOL AI INDEX',
        'summary_tab' => 'summary',
        'main_collection_id' => '690cad6eab0ece488bc031fe',
        'summary_collection_id' => '693ad5d52d322bf25c471b04',
    ],
    'earning' => [
        'spreadsheet_id' => env('GOOGLE_SHEET_ID_EARNING'),
        'main_tab' => 'Earnings',
        'summary_tab' => 'summary',
    //     'main_collection_id' => '69ebadaf15d99adacb640930', copy aitesting cred...
    //    'main_collection_id' => '69009b55146187907021f744',testing cred...
        'main_collection_id' => '69456480a59c84fcaa7103dc',
        'summary_collection_id' => '693827156441de7e67a1aa33',
    ],
    'valuation' => [
        'spreadsheet_id' => env('GOOGLE_SHEET_ID_VALUATION'),
        'main_tab' => 'VALUATIONS AI INDEX',
        'summary_tab' => 'summary',
        'main_collection_id' => '690cab4a10a078392eb1af51',
        'summary_collection_id' => '693ad3f41ada5bc98e98f74e',
    ],
];
