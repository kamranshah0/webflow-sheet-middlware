<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\WebflowService;

class ProcessWebflowChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected $collectionId;
    protected $chunk;
    protected $existingItems;
    protected $validFieldSlugs;

    /**
     * Create a new job instance.
     */
    public function __construct($collectionId, $chunk, $existingItems, $validFieldSlugs)
    {
        $this->collectionId = $collectionId;
        $this->chunk = $chunk;
        $this->existingItems = $existingItems;
        $this->validFieldSlugs = $validFieldSlugs;
    }

    /**
     * Execute the job.
     */
    public function handle(WebflowService $webflowService): void
    {
        $created = 0;
        $updated = 0;
        $errors = 0;

        Log::info("Starting chunk processing for Collection: {$this->collectionId}");

        foreach ($this->chunk as $row) {
            // Use 'ID' column as the primary unique identifier as requested by the user
            $tradeId = $row['ID'] ?? $row['id'] ?? $row['Trade ID'] ?? $row['trade_id'] ?? $row['trade-id'] ?? null;
            
            if (!$tradeId || trim((string)$tradeId) === '') {
                continue; // Skip rows without a unique ID
            }

            // Support 'TICKER' for Earnings sheet and 'SYMBOL' for others
            $symbol = $row['TICKER'] ?? $row['Ticker'] ?? $row['ticker'] ?? $row['SYMBOL'] ?? $row['symbol'] ?? 'Unknown';
            
            $slug = Str::slug($symbol . '-' . $tradeId . '-' . uniqid());
            $name = $row['COMPANY'] ?? $row['Company'] ?? $row['name'] ?? $symbol;

            // In Webflow, the field with display name "Trade ID" has the slug "equity-value"
            $fields = [
                "name" => (string) $name,
                "slug" => (string) $slug,
                "equity-value" => (string) $tradeId,
            ];

            $mainFieldMap = [
                'ticker' => 'symbol',
                'pre-marketafter-hours' => 'pre-market-after-hours',
                'of-shares-traded' => 'of-shares-trades',
                'net-profitloss' => 'net-profit-loss',
                'invested-capitalpremium-received' => 'invested-capital-premium-received',
                'gross-profit-loss' => 'gross-profit-loss',
            ];

            foreach ($row as $key => $value) {
                $webflowKey = Str::slug($key);
                
                if (isset($mainFieldMap[$webflowKey])) {
                    $webflowKey = $mainFieldMap[$webflowKey];
                }
                
                // Skip certain essential fields that are already set
                if (in_array($webflowKey, ['name', 'slug', 'equity-value', 'trade-id', 'id'])) {
                    continue;
                }

                // DYNAMIC FILTER: Only add the field if it exists in the Webflow Collection Schema
                if (in_array($webflowKey, $this->validFieldSlugs)) {
                    $fields[$webflowKey] = is_null($value) ? '' : (string) $value;
                }
            }

            try {
                if (isset($this->existingItems[(string)$tradeId])) {
                    // UPDATE
                    $webflowService->updateItem($this->collectionId, $this->existingItems[(string)$tradeId], $fields);
                    $updated++;
                } else {
                    // CREATE
                    $webflowService->createItem($this->collectionId, $fields);
                    $created++;
                }

                // Rate limit prevention (0.3s)
                usleep(300000); 
            } catch (\Exception $e) {
                Log::error("Error processing Trade ID {$tradeId}: " . $e->getMessage());
                $errors++;
            }
        }

        Log::info("Chunk processing finished. Created: {$created}, Updated: {$updated}, Errors: {$errors}");

        // Publish after the chunk finishes processing
        Log::info("Publishing Webflow Site after chunk completion...");
        $webflowService->publishSite();
        Log::info("Webflow Site Published Successfully.");
    }
}
