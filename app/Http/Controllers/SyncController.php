<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\SyncWebflowJob;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Handle the incoming request from Pabbly.
     * 
     * Expects JSON:
     * {
     *   "category": "dark_pool", // dark_pool, earning, valuation
     *   "type": "main" // main, summary
     * }
     */
    public function handle(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|in:dark_pool,earning,valuation',
            'type' => 'required|string|in:main,summary',
        ]);

        Log::info("Received sync request for Category={$validated['category']}, Type={$validated['type']}");

        $category = $validated['category'];
        $type = $validated['type'];

        // Validate mapping exists
        $mapping = config("sync.{$category}");
        
        if (!$mapping) {
            Log::warning("Received sync request with invalid mapping: Category={$category}");
            return response()->json([
                'error' => 'Invalid category mapping.'
            ], 400);
        }

        // Concurrency Control & Cooldown Check
        $lastJob = \App\Models\SyncJob::where('category', $category)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastJob) {
            // Check if job is currently running
            if (in_array($lastJob->status, ['queued', 'processing'])) {
                Log::info("Ignoring sync request: Job already running for Category={$category}, Type={$type}");
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'A sync for this category and type is already in progress.'
                ]);
            }

            // Check 10-minute cooldown for completed jobs (ONLY FOR MAIN TYPE)
            if ($type !== 'summary' && $lastJob->status === 'completed' && $lastJob->created_at->diffInMinutes(now()) < 10) {
                Log::info("Ignoring sync request: Cooldown active for Category={$category}, Type={$type}");
                return response()->json([
                    'status' => 'ignored',
                    'message' => 'Cooldown active. Please wait 10 minutes between sync requests.'
                ]);
            }
        }

        // Dispatch the background job
        SyncWebflowJob::dispatch($category, $type);

        Log::info("Dispatched SyncWebflowJob for Category={$category}, Type={$type}");

        // Return immediately
        return response()->json([
            'status' => 'queued'
        ]);
    }
}
