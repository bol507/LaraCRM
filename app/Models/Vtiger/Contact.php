<?php

namespace App\Models\Vtiger;

use App\Observers\Traits\LogsToVtiger;

/**
 * Model for vtiger_contactdetails table.
 * 
 * Represents a contact person entity in Vtiger CRM.
 * Automatically logs activities to vtiger_modtracker_basic.
 * 
 * @package App\Models\Vtiger
 * @property int $contactid Primary key
 * @property string $lastname Contact last name
 * @property string|null $firstname Contact first name
 * @property int|null $accountid Linked client ID
 * @property string|null $email Email address
 * @property string|null $mobile Mobile phone
 * @property string|null $title Job title
 * @property string $createdtime Creation timestamp
 * @property string $modifiedtime Last modification timestamp
 */
class Contact extends BaseModel
{
    use LogsToVtiger;

    protected $table = 'vtiger_contactdetails';
    protected $primaryKey = 'contactid';

    protected $fillable = [
        'lastname',
        'firstname',
        'accountid',
        'email',
        'mobile',
        'phone',
        'title',
        'department',
        'description',
    ];

    protected $casts = [
        'contactid' => 'integer',
        'accountid' => 'integer',
        'createdtime' => 'datetime',
        'modifiedtime' => 'datetime',
    ];

    public function getVtigerModule(): string
    {
        return 'Contacts';
    }

    /**
     * Scope a query to filter by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('accountid', $clientId);
    }

    /**
     * Scope a query to search by name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('firstname', 'LIKE', "%{$search}%")
              ->orWhere('lastname', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Get the linked client relationship.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'accountid', 'accountid');
    }

    /**
     * Get the full name of the contact.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /**
     * Get the entity name for activity logging.
     */
    public function getEntityNameAttribute(): string
    {
        return $this->full_name;
    }
}