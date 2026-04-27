<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$token = env('WEBFLOW_TOKEN');
$collectionId = '69456480a59c84fcaa7103dc'; // earnings main

// Fetch 1 item
$response = Illuminate\Support\Facades\Http::withToken($token)
    ->withHeaders(['accept-version' => '1.0.0'])
    ->get("https://api.webflow.com/v2/collections/{$collectionId}/items", ['limit' => 1]);

if (!$response->successful()) {
    echo "Fetch failed\n";
    exit;
}

$items = $response->json()['items'] ?? [];
if (empty($items)) {
    echo "No items\n";
    exit;
}

$itemId = $items[0]['id'];
echo "Publishing Item: $itemId\n";

$pubResponse = Illuminate\Support\Facades\Http::withToken($token)
    ->withHeaders(['accept-version' => '1.0.0'])
    ->post("https://api.webflow.com/v2/collections/{$collectionId}/items/publish", [
        'itemIds' => [$itemId]
    ]);

if ($pubResponse->successful()) {
    echo "Publish success!\n";
} else {
    echo "Publish failed: " . $pubResponse->body() . "\n";
}

