<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $label = 'ping') {}

    public function handle(): void
    {
        Log::info('Queue worker processed a job.', ['label' => $this->label]);
    }
}