<?php

namespace App\Models\Vtiger;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for all Vtiger CRM models.
 * 
 * Centralizes common configuration:
 * - Vtiger database connection
 * - Disabled Laravel timestamps (Vtiger uses its own)
 * - Disabled incrementing IDs (Vtiger uses manual IDs)
 * 
 * @package App\Models\Vtiger
 */
abstract class BaseModel extends Model
{
    /**
     * Connection name for Vtiger database.
     * 
     * @var string
     */
    protected $connection = 'vtiger';

    /**
     * Indicates if the model should be timestamped.
     * Vtiger manages its own created/modified times.
     * 
     * @var bool
     */
    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     * Vtiger uses manual ID assignment.
     * 
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     * 
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Get the Vtiger module name for this model.
     * 
     * Used by the LogsToVtiger trait to register activities.
     * 
     * @return string
     */
    abstract public function getVtigerModule(): string;
}