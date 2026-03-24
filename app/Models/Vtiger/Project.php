<?php

namespace App\Models\Vtiger;

use App\Observers\Traits\LogsToVtiger;

/**
 * Model for vtiger_project table.
 * 
 * Represents a project entity in Vtiger CRM.
 * Automatically logs activities to vtiger_modtracker_basic.
 * 
 * @package App\Models\Vtiger
 * @property int $projectid Primary key
 * @property string $projectname Project name
 * @property string $projectstatus Project status
 * @property string $projectpriority Project priority
 * @property int|null $linktoaccountscontacts Linked client ID
 * @property string|null $description Project description
 * @property string|null $targetenddate Target end date
 * @property string $createdtime Creation timestamp
 * @property string $modifiedtime Last modification timestamp
 */
class Project extends BaseModel
{
    use LogsToVtiger;

    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'vtiger_project';

    /**
     * The primary key for the model.
     * 
     * @var string
     */
    protected $primaryKey = 'projectid';

    /**
     * The attributes that are mass assignable.
     * 
     * @var array<int, string>
     */
    protected $fillable = [
        'projectname',
        'projectstatus',
        'projectpriority',
        'linktoaccountscontacts',
        'description',
        'targetenddate',
        'estimatedwork',
        'actualwork',
        'targetbudget',
        'actualbudget',
        'projecturl',
    ];

    /**
     * The attributes that should be cast.
     * 
     * @var array<string, string>
     */
    protected $casts = [
        'projectid' => 'integer',
        'linktoaccountscontacts' => 'integer',
        'targetenddate' => 'date',
        'createdtime' => 'datetime',
        'modifiedtime' => 'datetime',
    ];

    /**
     * Get the Vtiger module name.
     * 
     * @return string
     */
    public function getVtigerModule(): string
    {
        return 'Project';
    }

    /**
     * Scope a query to only include active projects.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('projectstatus', '!=', 'canceled');
    }

    /**
     * Scope a query to filter by client.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $clientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('linktoaccountscontacts', $clientId);
    }

    /**
     * Get the linked client relationship.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'linktoaccountscontacts', 'accountid');
    }

    /**
     * Get the project tasks relationship.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'linktoproject', 'projectid');
    }

    /**
     * Get the entity name for activity logging.
     * 
     * @return string
     */
    public function getEntityNameAttribute(): string
    {
        return $this->projectname;
    }
}