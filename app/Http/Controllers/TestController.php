<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    public function handle(Request $request)
    {
        // 🔥 FIX 1: Increase execution time
        set_time_limit(300);

        $rows = $request->input('data');

        $collectionId = env('WEBFLOW_COLLECTION_ID');
        $siteId = env('WEBFLOW_SITE_ID');
        $token = env('WEBFLOW_TOKEN');

        $existingItems = [];

        /**
         * 🔥 STEP 1: FETCH EXISTING ITEMS (PAGINATION SAFE)
         */
        try {

            $offset = 0;
            $limit = 100;

            do {
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->retry(3, 1000)
                    ->withHeaders(['accept-version' => '1.0.0'])
                    ->get("https://api.webflow.com/v2/collections/{$collectionId}/items", [
                        'offset' => $offset,
                        'limit' => $limit
                    ]);

                if (!$response->successful()) {
                    Log::error('Fetch failed', $response->json());
                    break;
                }

                $items = $response->json()['items'] ?? [];

                foreach ($items as $item) {
                    $tradeId = $item['fieldData']['trade-id'] ?? null;

                    if ($tradeId) {
                        $existingItems[(string)$tradeId] = $item['id'];
                    }
                }

                $offset += $limit;

            } while (count($items) === $limit);

        } catch (\Exception $e) {
            Log::error('Fetch Error: ' . $e->getMessage());
        }

        /**
         * 🔥 STEP 2: PROCESS IN CHUNKS (VERY IMPORTANT)
         */
        $chunks = array_chunk($rows, 20); // 👈 20 rows per batch

        foreach ($chunks as $chunkIndex => $chunk) {

            foreach ($chunk as $row) {

                $tradeId = (string) ($row['Trade ID'] ?? '');
                if (!$tradeId) continue;

                $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $tradeId));

                $fields = [
                    "name" => $row['COMPANY'] ?? 'No Name',
                    "slug" => $slug,
                    "trade-id" => $tradeId,

                    "symbol" => $row['SYMBOL'] ?? '',
                    "company" => $row['COMPANY'] ?? '',
                    "pre-market-after-hours" => $row['PRE-MARKET/AFTER HOURS'] ?? '',
                    "earnings-date" => $row['EARNINGS DATE'] ?? '',
                    "truthsayer-ai-signal" => $row['TRUTHSAYER AI SIGNAL'] ?? '',
                    "entry-date" => $row['ENTRY DATE'] ?? '',
                    "of-shares-trades" => $row['# OF SHARES TRADED'] ?? '',
                    "entry-price" => $row['ENTRY PRICE'] ?? '',
                    "invested-capital-premium-received" => $row['INVESTED CAPITAL/PREMIUM RECEIVED'] ?? '',
                    "current-price" => $row['CURRENT PRICE'] ?? '',
                    "exit-price" => $row['EXIT PRICE'] ?? '',
                    "exit-capital" => $row['EXIT CAPITAL'] ?? '',
                    "exit-date" => $row['EXIT DATE'] ?? '',
                    "gross-profit-loss" => $row['GROSS PROFIT / LOSS'] ?? '',
                    "commissions" => $row['COMMISSIONS'] ?? '',
                    "equity-value" => $row['EQUITY VALUE'] ?? '',
                    "net-profit-loss" => $row['NET PROFIT/LOSS'] ?? '',
                    "no-of-days" => $row['NO OF DAYS'] ?? '',
                    "roi" => $row['ROI'] ?? '',
                    "entry-alert" => $row['ENTRY ALERT'] ?? '',
                    "exit-alert" => $row['EXIT ALERT'] ?? '',
                    "status" => $row['STATUS'] ?? '',
                ];

                $fields = array_map(fn($v) => is_null($v) ? '' : (string) $v, $fields);

                try {

                    if (isset($existingItems[$tradeId])) {

                        // 🔄 UPDATE
                        Http::withToken($token)
                            ->timeout(30)
                            ->retry(3, 1000)
                            ->withHeaders(['accept-version' => '1.0.0'])
                            ->patch("https://api.webflow.com/v2/collections/{$collectionId}/items/" . $existingItems[$tradeId], [
                                "fieldData" => $fields
                            ]);

                        Log::info("UPDATED {$tradeId}");

                    } else {

                        // ➕ CREATE
                        Http::withToken($token)
                            ->timeout(30)
                            ->retry(3, 1000)
                            ->withHeaders(['accept-version' => '1.0.0'])
                            ->post("https://api.webflow.com/v2/collections/{$collectionId}/items", [
                                "fieldData" => $fields,
                                "isDraft" => false,
                                "isArchived" => false
                            ]);

                        Log::info("CREATED {$tradeId}");
                    }

                    // 🔥 FIX 2: prevent rate limit + timeout
                    usleep(300000); // 0.3 sec delay

                } catch (\Exception $e) {
                    Log::error("Error {$tradeId}: " . $e->getMessage());
                }
            }

            // 🔥 FIX 3: chunk delay (VERY IMPORTANT)
            sleep(2);
        }

        /**
         * 🔥 STEP 3: PUBLISH
         */
        try {
            Http::withToken($token)
                ->timeout(30)
                ->retry(3, 1000)
                ->withHeaders(['accept-version' => '1.0.0'])
                ->post("https://api.webflow.com/v2/sites/{$siteId}/publish", [
                    "publishToWebflowSubdomain" => true
                ]);

        } catch (\Exception $e) {
            Log::error('Publish Error: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'completed',
            'total' => count($rows),
            'chunks' => count($chunks)
        ]);
    }
}