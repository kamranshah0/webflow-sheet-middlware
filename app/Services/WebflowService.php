<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebflowService
{
    private $token;
    private $siteId;

    public function __construct()
    {
        $this->token = env('WEBFLOW_TOKEN');
        $this->siteId = env('WEBFLOW_SITE_ID');
    }

    /**
     * Fetch the schema for a specific Webflow collection.
     * Returns an array of field slugs.
     */
    public function getCollectionSchema($collectionId)
    {
        $response = Http::withToken($this->token)
            ->timeout(30)
            ->retry(3, 1000)
            ->withHeaders(['accept-version' => '1.0.0'])
            ->get("https://api.webflow.com/v2/collections/{$collectionId}");

        if (!$response->successful()) {
            Log::error("Webflow Fetch Schema failed for Collection {$collectionId}", $response->json());
            return [];
        }

        $fields = $response->json()['fields'] ?? [];
        return array_map(function($field) {
            return $field['slug'];
        }, $fields);
    }

    /**
     * Fetch all items from a given Webflow collection.
     * Uses pagination to get all records and returns an array keyed by trade-id.
     */
    public function getAllItems($collectionId)
    {
        $existingItems = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = Http::withToken($this->token)
                ->timeout(30)
                ->retry(3, 1000)
                ->withHeaders(['accept-version' => '1.0.0'])
                ->get("https://api.webflow.com/v2/collections/{$collectionId}/items", [
                    'offset' => $offset,
                    'limit' => $limit
                ]);

            if (!$response->successful()) {
                Log::error("Webflow Fetch failed for Collection {$collectionId}", $response->json());
                break;
            }

            $items = $response->json()['items'] ?? [];

            foreach ($items as $item) {
                $fieldData = $item['fieldData'] ?? [];
                // Support multiple possible keys for trade id based on Webflow's slug format
                $tradeId = $fieldData['equity-value'] ?? $fieldData['trade-id'] ?? $fieldData['trade_id'] ?? null;

                if ($tradeId) {
                    $existingItems[(string)$tradeId] = $item['id'];
                }
            }

            $offset += $limit;

        } while (count($items) === $limit);

        return $existingItems;
    }

    /**
     * Create a new item in Webflow Collection
     */
    public function createItem($collectionId, $fieldData)
    {
        return Http::withToken($this->token)
            ->timeout(30)
            ->retry(3, 1000)
            ->withHeaders(['accept-version' => '1.0.0'])
            ->post("https://api.webflow.com/v2/collections/{$collectionId}/items", [
                "fieldData" => $fieldData,
                "isDraft" => false,
                "isArchived" => false
            ]);
    }

    /**
     * Update an existing item in Webflow Collection
     */
    public function updateItem($collectionId, $itemId, $fieldData)
    {
        return Http::withToken($this->token)
            ->timeout(30)
            ->retry(3, 1000)
            ->withHeaders(['accept-version' => '1.0.0'])
            ->patch("https://api.webflow.com/v2/collections/{$collectionId}/items/{$itemId}", [
                "fieldData" => $fieldData
            ]);
    }

    /**
     * Publish the Webflow Site
     */
    public function publishSite()
    {
        return Http::withToken($this->token)
            ->timeout(30)
            ->retry(3, 1000)
            ->withHeaders(['accept-version' => '1.0.0'])
            ->post("https://api.webflow.com/v2/sites/{$this->siteId}/publish", [
                "publishToWebflowSubdomain" => true
            ]);
    }
}
