<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // add projectid to vtiger_purchaseorder
        DB::connection('vtiger')->statement('
            ALTER TABLE vtiger_purchaseorder 
            ADD COLUMN projectid INT(11) NULL AFTER vendorid,
            ADD INDEX idx_projectid (projectid)
        ');

        // add projectid to vtiger_purchaseordercf
        DB::connection('vtiger')->statement('
            ALTER TABLE vtiger_purchaseordercf 
            ADD COLUMN cf_projectid INT(11) NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('vtiger')->statement('
            ALTER TABLE vtiger_purchaseorder 
            DROP COLUMN projectid,
            DROP INDEX idx_projectid
        ');

        DB::connection('vtiger')->statement('
            ALTER TABLE vtiger_purchaseordercf 
            DROP COLUMN cf_projectid
        ');
    }
};