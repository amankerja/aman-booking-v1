<?php

namespace App\Console\Commands;

use App\Domain\Workflow\Services\WorkflowRunnerService;
use Illuminate\Console\Command;

class ProcessWorkflowDelaysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflows:process-delays';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending delayed workflow nodes that have reached their scheduled run_at time';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowRunnerService $runner): int
    {
        $count = $runner->processPendingDelays();
        if ($count > 0) {
            $this->info("Diproses {$count} node jeda alur kerja yang telah jatuh tempo.");
        } else {
            $this->comment('Tidak ada node jeda alur kerja yang menunggu jatuh tempo.');
        }

        return self::SUCCESS;
    }
}
