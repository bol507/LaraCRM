<?php

namespace App\Models\Vtiger;

use App\Observers\Traits\LogsToVtiger;

/**
 * Model for vtiger_account table.
 * 
 * Represents a client/account entity in Vtiger CRM.
 * Automatically logs activities to vtiger_modtracker_basic.
 * 
 * @package App\Models\Vtiger
 * @property int $accountid Primary key
 * @property string $accountname Client name
 * @property string|null $industry Industry type
 * @property string|null $annualrevenue Annual revenue
 * @property string|null $noofemployees Number of employees
 * @property string|null $phone Phone number
 * @property string|null $email Email address
 * @property string|null $website Website URL
 * @property string $createdtime Creation timestamp
 * @property string $modifiedtime Last modification timestamp
 */
class Client extends BaseModel
{
    use LogsToVtiger;

    protected $table = 'vtiger_account';
    protected $primaryKey = 'accountid';

    protected $fillable = [
        'accountname',
        'industry',
        'annualrevenue',
        'noofemployees',
        'phone',
        'email',
        'website',
        'bill_city',
        'bill_state',
        'bill_country',
        'bill_code',
        'description',
        'account_no',
    ];

    protected $casts = [
        'accountid' => 'integer',
        'annualrevenue' => 'float',
        'noofemployees' => 'integer',
        'createdtime' => 'datetime',
        'modifiedtime' => 'datetime',
    ];

    public function getVtigerModule(): string
    {
        return 'Accounts';
    }

    /**
     * Scope a query to filter by industry.
     */
    public function scopeByIndustry($query, string $industry)
    {
        return $query->where('industry', $industry);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('accountname', 'LIKE', "%{$search}%");
    }

    /**
     * Get the projects relationship.
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'linktoaccountscontacts', 'accountid');
    }

    /**
     * Get the quotes relationship.
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class, 'accountid', 'accountid');
    }

    /**
     * Get the entity name for activity logging.
     */
    public function getEntityNameAttribute(): string
    {
        return $this->accountname;
    }
}