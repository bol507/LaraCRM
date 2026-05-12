<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'vtiger';
    
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('nova_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('project_id')->nullable();
            $table->string('type'); // 'material_request_created', 'quote_accepted', etc.
            $table->string('title');
            $table->text('message');
            $table->string('icon')->default('Bell'); // Icon to display in frontend
            $table->string('severity')->default('info'); // 'info' | 'success' | 'warning' | 'error'
            
            // Related entity (for deep-linking)
            $table->string('entity_type')->nullable(); // 'material_request', 'vendor_quote'
            $table->unsignedBigInteger('entity_id')->nullable();
            
            // Recipients
            $table->json('recipient_ids'); // [1, 5, 12] - User IDs
            $table->json('recipient_roles')->nullable(); // ['Purchasing', 'Administrator']
            
            // Status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            
            $table->unsignedInteger('created_by');
            $table->timestamps();
            
            $table->index(['project_id', 'is_read']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('nova_notifications');
    }
};