<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\GoogleSheetsService;
use App\Services\WebflowService;
use App\Models\SyncJob;

class SyncWebflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // Allow 10 minutes to process
    public $tries = 3;

    protected $category;
    protected $type;
    protected $syncJobId;

    /**
     * Create a new job instance.
     */
    public function __construct($category, $type)
    {
        $this->category = $category;
        $this->type = $type;
        
        // Create the DB record immediately when dispatched
        $syncJob = SyncJob::create([
            'category' => $category,
            'type' => $type,
            'status' => 'queued',
            'started_at' => now(),
        ]);
        $this->syncJobId = $syncJob->id;
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleSheetsService $sheetsService, WebflowService $webflowService): void
    {
        $syncJob = SyncJob::find($this->syncJobId);
        if ($syncJob) {
            $syncJob->update(['status' => 'processing']);
        }

        Log::info("Starting SyncWebflowJob for Category: {$this->category}, Type: {$this->type}");

        // 1. Resolve Mapping
        $mapping = config("sync.{$this->category}");
        
        if (!$mapping) {
            Log::error("No mapping found for Category: {$this->category}");
            if ($syncJob) $syncJob->update(['status' => 'failed', 'error_log' => "No config mapping found"]);
            return;
        }

        $spreadsheetId = $mapping['spreadsheet_id'];
        
        // Determine which tab and collection to use based on type
        if ($this->type === 'main') {
            $sheetName = $mapping['main_tab'];
            $collectionId = $mapping['main_collection_id'];
        } else {
            $sheetName = $mapping['summary_tab'];
            $collectionId = $mapping['summary_collection_id'];
        }

        try {
            // 2. Fetch Data
            Log::info("Fetching data from Google Sheet: {$sheetName} (Spreadsheet ID: {$spreadsheetId})");
            $rows = $sheetsService->getSheetData($spreadsheetId, $sheetName);
            $totalRows = count($rows);
            Log::info("Found " . $totalRows . " rows in {$sheetName}");

            if ($syncJob) {
                $syncJob->update(['total_rows' => $totalRows]);
            }

            if (empty($rows)) {
                Log::info("No data found in {$sheetName}. Exiting.");
                if ($syncJob) $syncJob->update(['status' => 'completed', 'completed_at' => now()]);
                return;
            }

            // Stats
            $created = 0;
            $updated = 0;
            $errors = 0;
            $skipped = 0;

            if ($this->type === 'main') {
                // 3. Fetch Webflow Schema to only send valid fields
                Log::info("Fetching Webflow schema for Collection: {$collectionId}");
                $validFieldSlugs = $webflowService->getCollectionSchema($collectionId);
                Log::info("Valid fields in Webflow: " . implode(', ', $validFieldSlugs));

                // 4. Fetch Webflow Items (Bulk)
                Log::info("Fetching existing Webflow items for Collection: {$collectionId}");
                $existingItems = $webflowService->getAllItems($collectionId);
                Log::info("Found " . count($existingItems) . " existing Webflow items");

                // PRE-FILTER ROWS: Remove rows with empty IDs to avoid unnecessary chunks
                $validRows = [];
                foreach ($rows as $row) {
                    $tradeId = $row['ID'] ?? $row['id'] ?? $row['Trade ID'] ?? $row['trade_id'] ?? $row['trade-id'] ?? null;
                    if (!$tradeId || trim((string)$tradeId) === '') {
                        $skipped++;
                    } else {
                        $validRows[] = $row;
                    }
                }

                if ($syncJob) {
                    $syncJob->update([
                        'skipped_count' => $skipped,
                        'processed_rows' => $skipped
                    ]);
                }

                // 5. Perform Upsert in Chunks (Bulk)
                $chunks = array_chunk($validRows, 100);
                
                if ($syncJob) {
                    $syncJob->update(['total_chunks' => count($chunks)]);
                }

                foreach ($chunks as $chunkIndex => $chunk) {
                    Log::info("Dispatching chunk " . ($chunkIndex + 1) . " of " . count($chunks) . " to queue.");
                    
                    $chunkId = null;
                    if ($syncJob) {
                        $chunkRecord = \App\Models\SyncJobChunk::create([
                            'sync_job_id' => $this->syncJobId,
                            'chunk_index' => $chunkIndex + 1,
                            'status' => 'pending',
                            'total_rows' => count($chunk),
                        ]);
                        $chunkId = $chunkRecord->id;
                    }

                    ProcessWebflowChunkJob::dispatch($collectionId, $chunk, $existingItems, $validFieldSlugs, $this->syncJobId, $chunkId);
                }

            } elseif ($this->type === 'summary') {
                // 3. Fetch Webflow Schema
                $validFieldSlugs = $webflowService->getCollectionSchema($collectionId);

                // Single logic
                $row = $rows[0]; // Only use first data row
                Log::info("Processing single record update for summary.");

                $slug = 'summary';
                $name = $row['NAME'] ?? $row['Name'] ?? $row['name'] ?? 'Summary';

                $fields = [
                    "name" => (string) $name,
                    "slug" => (string) $slug,
                ];

                $summaryFieldMap = [
                    'start-date' => 'start-date-2',
                    'initial-capital' => 'initial-capital-2',
                    'current-capital' => 'current-capital-2',
                    'equity-value' => 'equity-value-2',
                    'current-cash-position' => 'current-cash-position-2',
                    'current-cash-p' => 'current-cash-position-2',
                    'roi' => 'roi-2',
                    'benchmark-roi-s-p-500' => 'benchmark-roi-s-p-500-2',
                    'benchmark-roi-sp-500' => 'benchmark-roi-s-p-500-2',
                    'truthsayer-ai-beta' => 'truthsayer-ai-beta-2',
                    'truthsayer-ai-alpha' => 'truthsayer-ai-alpha-2',
                    'truthsayer-ai-sharpe-ratio' => 'truthsayer-ai-sharpe-ratio',
                    'truthsayer-ai-win-rate' => 'truthsayer-ai-win-rate-2',
                    'average-days-per-trade' => 'no-of-closed-trades-2',
                    'no-of-closed-trades' => 'no-of-closed-trades-2',
                ];

                foreach ($row as $key => $value) {
                    $webflowKey = Str::slug($key);
                    
                    if (isset($summaryFieldMap[$webflowKey])) {
                        $webflowKey = $summaryFieldMap[$webflowKey];
                    }

                    if (in_array($webflowKey, ['name', 'slug'])) {
                        continue;
                    }

                    // DYNAMIC FILTER
                    if (in_array($webflowKey, $validFieldSlugs)) {
                        $val = is_null($value) ? '' : (string) $value;
                        
                        // Automatically format date fields for Webflow
                        if (str_contains($webflowKey, 'date') && $val !== '') {
                            try {
                                $val = \Carbon\Carbon::parse($val)->format('Y-m-d');
                            } catch (\Exception $e) {
                                // Keep original if parsing fails
                            }
                        }
                        
                        $fields[$webflowKey] = $val;
                    }
                }

                try {
                    // Check if summary item exists. Since it's single, we can fetch all to find it or query it.
                    // The getAllItems method returns map of trade-id to id. We can't use that for summary if it has no trade-id.
                    // We will just fetch items and find the one with slug 'summary'.
                    Log::info("Fetching items to find summary record...");
                    
                    // We modify how we find it since WebflowService->getAllItems returns keyed by trade-id.
                    // We'll create a quick custom fetch or just fetch the first page.
                    // Let's use the Http facade directly here to get items if we just need to search by slug
                    $token = env('WEBFLOW_TOKEN');
                    $response = \Illuminate\Support\Facades\Http::withToken($token)
                        ->timeout(30)
                        ->withHeaders(['accept-version' => '1.0.0'])
                        ->get("https://api.webflow.com/v2/collections/{$collectionId}/items", [
                            'limit' => 100 // Summary is likely in the first 100
                        ]);

                    $itemId = null;
                    if ($response->successful()) {
                        $items = $response->json()['items'] ?? [];
                        // Check if any item exists in this summary collection
                        if (count($items) > 0) {
                            $itemId = $items[0]['id']; // Just take the very first item and update it
                        }
                    }

                    if ($itemId) {
                        // UPDATE
                        $webflowService->updateItem($collectionId, $itemId, $fields);
                        $updated++;
                        Log::info("Summary record updated.");
                    } else {
                        // CREATE
                        $res = $webflowService->createItem($collectionId, $fields);
                        if ($res->successful()) {
                            $itemId = $res->json()['id'] ?? null;
                        }
                        $created++;
                        Log::info("Summary record created.");
                    }

                    if ($syncJob) {
                        $syncJob->increment('processed_rows');
                        if ($itemId) {
                            $syncJob->increment('updated_count');
                        } else {
                            $syncJob->increment('created_count');
                        }
                    }

                    if ($itemId) {
                        Log::info("Publishing Webflow Item for summary to all environments...");
                        $webflowService->publishItems($collectionId, [$itemId]);
                        Log::info("Webflow Item Published Successfully.");
                    }

                } catch (\Exception $e) {
                    $reason = $e->getMessage();
                    if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                        $reason = $e->response->body();
                    }
                    Log::error("Error processing single summary record: " . $reason);
                    $errors++;
                    if ($syncJob) {
                        $syncJob->increment('error_count');
                        $syncJob->update([
                            'error_log' => json_encode([
                                [
                                    'type' => 'error',
                                    'identifier' => 'Summary Record',
                                    'reason' => $reason
                                ]
                            ])
                        ]);
                    }
                }

                if ($syncJob) {
                    $syncJob->update(['status' => 'completed', 'completed_at' => now()]);
                }
            }

            // 6. Log success/fail
            Log::info("Sync completed for Category {$this->category}, Type {$this->type}. Total rows: {$totalRows}. Created: {$created}, Updated: {$updated}, Errors: {$errors}");

        } catch (\Exception $e) {
            Log::error("SyncWebflowJob Failed: " . $e->getMessage());
            if ($this->syncJobId) {
                SyncJob::find($this->syncJobId)?->update([
                    'status' => 'failed',
                    'error_log' => $e->getMessage()
                ]);
            }
        }
    }
}
