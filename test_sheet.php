<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sheetsService = app(\App\Services\GoogleSheetsService::class);
$spreadsheetId = env('GOOGLE_SHEET_ID_VALUATION');
$sheetName = 'VALUATIONS AI INDEX';

$rows = $sheetsService->getSheetData($spreadsheetId, $sheetName);

if (empty($rows)) {
    echo "No rows found\n";
    exit;
}

echo "First Row:\n";
print_r($rows[0]);

echo "\nHeaders:\n";
print_r(array_keys($rows[0]));

