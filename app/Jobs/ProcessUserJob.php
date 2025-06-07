<?php

namespace App\Jobs;

use App\DTOs\UserProcessDTO;
use App\Services\UserEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessUserJob implements ShouldQueue
{
    use Queueable;

    protected UserProcessDTO $dto;


    public function __construct(UserProcessDTO $dto)
    {
        $this->dto = $dto;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $status = app(UserEnrichmentService::class)->process($this->dto);

        logger()->info("Processing completed with status: $status");
    }
}
