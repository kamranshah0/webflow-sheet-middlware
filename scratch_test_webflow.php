<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WebflowService;

$service = app(WebflowService::class);

$collections = [
    'Earnings Main' => config('sync.earning.main_collection_id'),
];

foreach ($collections as $name => $collectionId) {
    echo "Testing Webflow Collection: {$name} (ID: {$collectionId})\n";
    try {
        $items = $service->getAllItems($collectionId);
        echo "Successfully fetched " . count($items) . " items from Webflow.\n";
        
        if (!empty($items)) {
            $firstItem = reset($items);
            echo "First item sample data (Trade ID: " . ($firstItem['fieldData']['equity-value'] ?? 'N/A') . "):\n";
            echo json_encode($firstItem['fieldData'] ?? $firstItem, JSON_PRETTY_PRINT) . "\n";
        }

        echo "\nFetching Schema...\n";
        $schema = $service->getCollectionSchema($collectionId);
        echo "Valid schema fields for this collection: " . count($schema) . " fields found.\n";
        echo json_encode($schema, JSON_PRETTY_PRINT) . "\n";

    } catch (\Exception $e) {
        echo "Error testing Webflow Collection {$name}: " . $e->getMessage() . "\n";
    }
    echo "-----------------------------------\n";
}
