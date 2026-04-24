<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\GoogleSheetsService;

$service = new GoogleSheetsService();

$sheets = [
    'dark_pool' => [
        'id' => config('sync.dark_pool.spreadsheet_id'),
        'tab' => 'DARK POOL AI INDEX'
    ],
    'earning' => [
        'id' => config('sync.earning.spreadsheet_id'),
        'tab' => 'Earnings'
    ],
    'valuation' => [
        'id' => config('sync.valuation.spreadsheet_id'),
        'tab' => 'VALUATIONS AI INDEX'
    ]
];

foreach ($sheets as $name => $config) {
    echo "Testing {$name} sheet...\n";
    try {
        $data = $service->getSheetData($config['id'], $config['tab']);
        echo "Successfully fetched " . count($data) . " rows from {$name}.\n";
        if (!empty($data)) {
            echo "First row sample: " . json_encode($data[0]) . "\n";
        }
    } catch (\Exception $e) {
        echo "Error testing {$name}: " . $e->getMessage() . "\n";
    }
    echo "-----------------------------------\n";
}
