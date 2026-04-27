<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'type',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'total_chunks',
        'processed_chunks',
        'started_at',
        'completed_at',
        'error_log',
    ];

    public function chunks()
    {
        return $this->hasMany(SyncJobChunk::class);
    }
}
