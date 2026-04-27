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
    protected $syncJobId;
    protected $chunkId;

    /**
     * Create a new job instance.
     */
    public function __construct($collectionId, $chunk, $existingItems, $validFieldSlugs, $syncJobId = null, $chunkId = null)
    {
        $this->collectionId = $collectionId;
        $this->chunk = $chunk;
        $this->existingItems = $existingItems;
        $this->validFieldSlugs = $validFieldSlugs;
        $this->syncJobId = $syncJobId;
        $this->chunkId = $chunkId;
    }

    /**
     * Execute the job.
     */
    public function handle(WebflowService $webflowService): void
    {
        if ($this->syncJobId) {
            $syncJob = \App\Models\SyncJob::find($this->syncJobId);
            if ($syncJob && $syncJob->status === 'cancelled') {
                Log::info("ProcessWebflowChunkJob skipped. SyncJob was cancelled. ID: {$this->syncJobId}");
                return; // Stop execution if cancelled
            }
        }

        if ($this->chunkId) {
            $chunk = \App\Models\SyncJobChunk::find($this->chunkId);
            if ($chunk && $chunk->status === 'cancelled') {
                Log::info("ProcessWebflowChunkJob skipped. Chunk was cancelled. ID: {$this->chunkId}");
                return;
            }

            $chunk->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);
        }
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $logs = []; // Collect detailed logs

        Log::info("Starting chunk processing for Collection: {$this->collectionId}");

        foreach ($this->chunk as $index => $row) {
            $rowNumber = $index + 1; // 1-based index within the chunk for easier tracking

            // Use 'ID' column as the primary unique identifier as requested by the user
            $tradeId = $row['ID'] ?? $row['id'] ?? $row['Trade ID'] ?? $row['trade_id'] ?? $row['trade-id'] ?? null;
            
            if (!$tradeId || trim((string)$tradeId) === '') {
                $skipped++;
                $logs[] = [
                    'type' => 'skip',
                    'identifier' => "Row {$rowNumber}",
                    'reason' => 'Missing Trade ID or ID is empty',
                    'data' => json_encode($row)
                ];
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
                    $val = is_null($value) ? '' : (string) $value;
                    
                    // Automatically format date fields for Webflow
                    if (str_contains($webflowKey, 'date') && $val !== '') {
                        try {
                            $val = \Carbon\Carbon::parse($val)->toIso8601ZuluString();
                        } catch (\Exception $e) {
                            // Keep original if parsing fails
                        }
                    }

                    $fields[$webflowKey] = $val;
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
                $reason = $e->getMessage();
                if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                    $reason = $e->response->body();
                }
                Log::error("Error processing Trade ID {$tradeId}: " . $reason);
                $errors++;
                $logs[] = [
                    'type' => 'error',
                    'identifier' => "Trade ID: {$tradeId}",
                    'reason' => $reason,
                    'data' => json_encode($fields)
                ];
            }
        }

        Log::info("Chunk processing finished. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}");

        if ($this->chunkId) {
            \App\Models\SyncJobChunk::where('id', $this->chunkId)->update([
                'status' => 'completed',
                'processed_rows' => count($this->chunk),
                'created_count' => $created,
                'updated_count' => $updated,
                'skipped_count' => $skipped,
                'error_count' => $errors,
                'completed_at' => now(),
                'error_log' => !empty($logs) ? json_encode($logs) : null,
            ]);
        }

        if ($this->syncJobId) {
            $syncJob = \App\Models\SyncJob::find($this->syncJobId);
            if ($syncJob) {
                // We use DB operations directly or increment to avoid race conditions
                $syncJob->increment('processed_rows', count($this->chunk));
                $syncJob->increment('created_count', $created);
                $syncJob->increment('updated_count', $updated);
                $syncJob->increment('skipped_count', $skipped);
                $syncJob->increment('error_count', $errors);
                $syncJob->increment('processed_chunks', 1);

                // If all chunks are processed, mark as completed
                $syncJob->refresh();
                if ($syncJob->processed_chunks >= $syncJob->total_chunks) {
                    $syncJob->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }
            }
        }

        // Publish after the chunk finishes processing
        Log::info("Publishing Webflow Site after chunk completion...");
        $webflowService->publishSite();
        Log::info("Webflow Site Published Successfully.");
    }
}
