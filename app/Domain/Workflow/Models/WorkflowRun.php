<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Booking\Models\Booking;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $workflow_id
 * @property int $version_id
 * @property int|null $booking_id
 * @property string $execution_id
 * @property string $trigger_event
 * @property array<string, mixed>|null $trigger_payload
 * @property string $status
 * @property string|null $current_node_id
 * @property int $depth
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Workflow $workflow
 * @property-read WorkflowVersion $version
 * @property-read Booking|null $booking
 * @property-read Collection<int, WorkflowLog> $logs
 */
class WorkflowRun extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'workflow_runs';

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'version_id',
        'booking_id',
        'execution_id',
        'trigger_event',
        'trigger_payload',
        'status',
        'current_node_id',
        'depth',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger_payload' => 'array',
            'depth' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'version_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    /**
     * @return HasMany<WorkflowLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class, 'workflow_run_id')->orderBy('id', 'asc');
    }
}
