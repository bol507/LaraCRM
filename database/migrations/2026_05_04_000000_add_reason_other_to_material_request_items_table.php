<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    protected $connection = 'vtiger';

    public function up(): void
    {
        Schema::connection($this->connection)->table('material_request_items', function (Blueprint $table) {
            $table->text('reason_other')->nullable()->after('catalog_reason_type')
                  ->comment('Custom justification when reason is "other"');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('material_request_items', function (Blueprint $table) {
            $table->dropColumn('reason_other');
        });
    }
};