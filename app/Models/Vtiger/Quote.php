<?php

namespace App\Models\Vtiger;

use App\Observers\Traits\LogsToVtiger;

/**
 * Model for vtiger_quotes table.
 * 
 * Represents a quote/proposal entity in Vtiger CRM.
 * Automatically logs activities to vtiger_modtracker_basic.
 * 
 * @package App\Models\Vtiger
 * @property int $quoteid Primary key
 * @property string $subject Quote subject/title
 * @property int|null $accountid Linked client ID
 * @property float|null $total Total amount
 * @property string|null $quotes_stage Quote stage
 * @property string|null $validtill Valid until date
 * @property string $createdtime Creation timestamp
 * @property string $modifiedtime Last modification timestamp
 */
class Quote extends BaseModel
{
    use LogsToVtiger;

    protected $table = 'vtiger_quotes';
    protected $primaryKey = 'quoteid';

    protected $fillable = [
        'subject',
        'accountid',
        'contactid',
        'total',
        'quotes_stage',
        'validtill',
        'description',
        'terms_conditions',
        'discount_amount',
        'discount_percent',
        'taxtype',
        'adjustmentType',
        'adjustment',
        'quote_no',
    ];

    protected $casts = [
        'quoteid' => 'integer',
        'accountid' => 'integer',
        'contactid' => 'integer',
        'total' => 'float',
        'discount_amount' => 'float',
        'discount_percent' => 'float',
        'adjustment' => 'float',
        'validtill' => 'date',
        'createdtime' => 'datetime',
        'modifiedtime' => 'datetime',
    ];

    public function getVtigerModule(): string
    {
        return 'Quotes';
    }

    /**
     * Scope a query to only include active quotes.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('quotes_stage', ['Rejected', 'Cancelled']);
    }

    /**
     * Scope a query to filter by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('accountid', $clientId);
    }

    /**
     * Scope a query to filter by stage.
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('quotes_stage', $stage);
    }

    /**
     * Get the linked client relationship.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'accountid', 'accountid');
    }

    /**
     * Get the entity name for activity logging.
     */
    public function getEntityNameAttribute(): string
    {
        return $this->subject;
    }

    /**
     * Format the total amount for display.
     */
    public function getFormattedTotalAttribute(): string
    {
        return $this->total ? '$' . number_format($this->total, 2) : '$0.00';
    }
}