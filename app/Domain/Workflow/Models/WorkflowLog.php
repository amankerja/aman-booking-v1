<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_run_id
 * @property string $node_id
 * @property string $node_type
 * @property string|null $node_label
 * @property string $status
 * @property int $attempt
 * @property array<string, mixed>|null $input_data
 * @property array<string, mixed>|null $output_data
 * @property string|null $error_message
 * @property Carbon|null $run_at
 * @property Carbon|null $executed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read WorkflowRun $run
 */
class WorkflowLog extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';
    public const STATUS_WAITING_DELAY = 'WAITING_DELAY';

    protected $table = 'workflow_logs';

    protected $fillable = [
        'tenant_id',
        'workflow_run_id',
        'node_id',
        'node_type',
        'node_label',
        'status',
        'attempt',
        'input_data',
        'output_data',
        'error_message',
        'run_at',
        'executed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'input_data' => 'array',
            'output_data' => 'array',
            'run_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}
