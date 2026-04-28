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

            // Get dynamic cooldown from settings or default to 10
            $cooldownMinutes = (int) \App\Models\Setting::where('key', "cooldown_{$category}")->value('value') ?: 10;

            // Check dynamic cooldown for completed jobs (ONLY FOR MAIN TYPE)
            if ($type !== 'summary' && $lastJob->status === 'completed' && $lastJob->created_at->diffInMinutes(now()) < $cooldownMinutes) {
                Log::info("Ignoring sync request: Cooldown active for Category={$category}, Type={$type}");
                return response()->json([
                    'status' => 'ignored',
                    'message' => "Cooldown active. Please wait {$cooldownMinutes} minutes between sync requests."
                ]);
            }
        }

        // Dispatch Background Job
        SyncWebflowJob::dispatch($category, $type);

        return response()->json([
            'status' => 'queued',
            'message' => 'Sync has been queued and is processing.'
        ]);
    }

    public function debugSheetData(Request $request, \App\Services\GoogleSheetsService $sheetsService)
    {
        $category = $request->query('category');
        $type = $request->query('type');

        if (!$category || !$type) {
            return response()->json(['error' => 'Missing category or type'], 400);
        }

        $mapping = config("sync.{$category}");
        
        if (!$mapping) {
            return response()->json(['error' => "No mapping found for Category: {$category}"], 404);
        }

        $spreadsheetId = $mapping['spreadsheet_id'];
        $sheetName = $type === 'main' ? $mapping['main_tab'] : $mapping['summary_tab'];

        try {
            $rows = $sheetsService->getSheetData($spreadsheetId, $sheetName);
            
            if (empty($rows)) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No data found in sheet.',
                    'spreadsheet_id' => $spreadsheetId,
                    'sheet_name' => $sheetName,
                ]);
            }

            $headers = array_keys($rows[0]);
            $top10 = array_slice($rows, 0, 10);

            return response()->json([
                'status' => 'success',
                'category' => $category,
                'type' => $type,
                'spreadsheet_id' => $spreadsheetId,
                'sheet_name' => $sheetName,
                'total_rows_found' => count($rows),
                'headers' => $headers,
                'fi rst_10_rows' => $top10
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch sheet data',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
