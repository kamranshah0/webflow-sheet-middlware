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

        // Dispatch the background job
        SyncWebflowJob::dispatch($category, $type);

        Log::info("Dispatched SyncWebflowJob for Category={$category}, Type={$type}");

        // Return immediately
        return response()->json([
            'status' => 'queued'
        ]);
    }
}
