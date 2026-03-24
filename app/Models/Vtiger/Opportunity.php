<?php

namespace App\Models\Vtiger;

use App\Observers\Traits\LogsToVtiger;

/**
 * Model for vtiger_potential table.
 * 
 * Represents a sales opportunity entity in Vtiger CRM.
 * Automatically logs activities to vtiger_modtracker_basic.
 * 
 * @package App\Models\Vtiger
 * @property int $potentialid Primary key
 * @property string $potentialname Opportunity name
 * @property int|null $related_to Linked client ID
 * @property float|null $amount Expected amount
 * @property string|null $sales_stage Sales stage
 * @property int|null $probability Probability percentage
 * @property string|null $closingdate Expected closing date
 * @property string $createdtime Creation timestamp
 * @property string $modifiedtime Last modification timestamp
 */
class Opportunity extends BaseModel
{
    use LogsToVtiger;

    protected $table = 'vtiger_potential';
    protected $primaryKey = 'potentialid';

    protected $fillable = [
        'potentialname',
        'related_to',
        'amount',
        'sales_stage',
        'probability',
        'closingdate',
        'description',
        'nextstep',
        'leadsource',
        'opportunity_no',
        'type',
    ];

    protected $casts = [
        'potentialid' => 'integer',
        'related_to' => 'integer',
        'amount' => 'float',
        'probability' => 'integer',
        'closingdate' => 'date',
        'createdtime' => 'datetime',
        'modifiedtime' => 'datetime',
    ];

    public function getVtigerModule(): string
    {
        return 'Potentials';
    }

    /**
     * Scope a query to only include open opportunities.
     */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost']);
    }

    /**
     * Scope a query to filter by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('related_to', $clientId);
    }

    /**
     * Scope a query to filter by stage.
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('sales_stage', $stage);
    }

    /**
     * Get the linked client relationship.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'related_to', 'accountid');
    }

    /**
     * Get the entity name for activity logging.
     */
    public function getEntityNameAttribute(): string
    {
        return $this->potentialname;
    }

    /**
     * Format the amount for display.
     */
    public function getFormattedAmountAttribute(): string
    {
        return $this->amount ? '$' . number_format($this->amount, 2) : '$0.00';
    }

    /**
     * Check if the opportunity is won.
     */
    public function isWon(): bool
    {
        return $this->sales_stage === 'Closed Won';
    }

    /**
     * Check if the opportunity is lost.
     */
    public function isLost(): bool
    {
        return $this->sales_stage === 'Closed Lost';
    }
}