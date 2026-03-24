<?php

namespace App\Models\Vtiger;

use App\Observers\Traits\LogsToVtiger;
use Carbon\Carbon;

/**
 * Model for vtiger_projecttask table.
 * 
 * Represents a project task entity in Vtiger CRM.
 * Automatically logs activities to vtiger_modtracker_basic.
 * 
 * @package App\Models\Vtiger
 * @property int $projecttaskid Primary key
 * @property string $projecttaskname Task name
 * @property int|null $linktoproject Linked project ID
 * @property string|null $status Task status
 * @property string|null $priority Task priority
 * @property string|null $targetenddate Target end date
 * @property string $createdtime Creation timestamp
 * @property string $modifiedtime Last modification timestamp
 */
class Task extends BaseModel
{
    use LogsToVtiger;

    protected $table = 'vtiger_projecttask';
    protected $primaryKey = 'projecttaskid';

    protected $fillable = [
        'projecttaskname',
        'linktoproject',
        'status',
        'priority',
        'targetenddate',
        'description',
        'assigned_user_id',
        'estimatedwork',
        'actualwork',
        'progress',
        'linktoaccountscontacts',
    ];

    protected $casts = [
        'projecttaskid' => 'integer',
        'linktoproject' => 'integer',
        'assigned_user_id' => 'integer',
        'linktoaccountscontacts' => 'integer',
        'estimatedwork' => 'float',
        'actualwork' => 'float',
        'progress' => 'integer',
        'targetenddate' => 'date',
        'createdtime' => 'datetime',
        'modifiedtime' => 'datetime',
    ];

    public function getVtigerModule(): string
    {
        return 'ProjectTask';
    }

    /**
     * Scope a query to only include open tasks.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', '!=', 'Completed');
    }

    /**
     * Scope a query to filter by project.
     */
    public function scopeByProject($query, int $projectId)
    {
        return $query->where('linktoproject', $projectId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get the linked project relationship.
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'linktoproject', 'projectid');
    }

    /**
     * Get the assigned user relationship.
     */
    public function assignedUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_user_id', 'id');
    }

    /**
     * Get the entity name for activity logging.
     */
    public function getEntityNameAttribute(): string
    {
        return $this->projecttaskname;
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        if (!$this->targetenddate) {
            return false;
        }

        
        $date = $this->targetenddate instanceof Carbon
            ? $this->targetenddate
            : Carbon::parse($this->targetenddate);

        return $date->isPast() && !$this->isCompleted();
    }
}
