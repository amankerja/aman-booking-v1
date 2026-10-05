<?php

namespace App\Domain\Workflow\Jobs;

use App\Domain\Workflow\Services\WorkflowRunnerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteWorkflowNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of attempts for this job.
     */
    public int $tries = 3;

    /**
     * Timeout for node execution in seconds.
     */
    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $statePayload
     */
    public function __construct(
        public int $runId,
        public string $nodeId,
        public array $statePayload = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WorkflowRunnerService $runner): void
    {
        $runner->executeNode($this->runId, $this->nodeId, $this->statePayload);
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // Failures are recorded into workflow_logs and workflow_runs within executeNode
    }
}
