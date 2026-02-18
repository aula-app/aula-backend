<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LegacyMkdir implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job for a specific Tenant Instance.
     */
    public function __construct(private string $instanceCode)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        mkdir("/mnt/aula-backend-legacy/files/{$this->instanceCode}");
    }
}
