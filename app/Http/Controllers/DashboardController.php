<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\SyncJob;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function apiData()
    {
        $jobs = SyncJob::with(['chunks' => function ($query) {
            $query->orderBy('chunk_index', 'asc');
        }])->orderBy('created_at', 'desc')->take(50)->get();
        return response()->json($jobs);
    }

    public function cancelJob($id)
    {
        $job = SyncJob::findOrFail($id);
        
        if (in_array($job->status, ['queued', 'processing'])) {
            $job->status = 'cancelled';
            $job->save();

            // Cancel all pending chunks as well
            \App\Models\SyncJobChunk::where('sync_job_id', $job->id)
                ->whereIn('status', ['pending', 'processing'])
                ->update(['status' => 'cancelled']);
        }

        return response()->json(['success' => true]);
    }

    public function resetCooldowns()
    {
        // Update recently completed 'main' jobs to 'completed_cleared' so they bypass the cooldown check
        \App\Models\SyncJob::where('status', 'completed')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->update(['status' => 'completed_cleared']);

        return response()->json(['success' => true]);
    }

    public function getSettings()
    {
        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($data['settings'] as $key => $value) {
            \App\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['success' => true]);
    }

    public function deleteJob($id)
    {
        $job = SyncJob::findOrFail($id);
        
        // Delete all associated chunks first
        \App\Models\SyncJobChunk::where('sync_job_id', $job->id)->delete();
        
        $job->delete();

        return response()->json(['success' => true]);
    }
}
