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
}
