<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncJobChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_job_id',
        'chunk_index',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'started_at',
        'completed_at',
        'error_log',
    ];

    public function syncJob()
    {
        return $this->belongsTo(SyncJob::class);
    }
}
