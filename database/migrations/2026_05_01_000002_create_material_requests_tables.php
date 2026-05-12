<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    protected $connection = 'vtiger'; 

    public function up(): void
    {
        Schema::connection($this->connection)->create('nova_material_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('project_id'); // FK a vtiger_project.projectid
            $table->unsignedInteger('requested_by'); // FK a vtiger_users.id
            $table->string('status')->default('draft'); // draft, submitted, approved, partially_procured, closed, rejected
            $table->datetime('submitted_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->datetime('approved_at')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
        });

        Schema::connection($this->connection)->create('nova_material_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id')->references('id')->on('nova_material_requests')->onDelete('cascade');

            $table->string('catalog_item_type')->default('material');
            $table->string('catalog_reason_type')->default('new_requirement');
            $table->string('item_name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->default('unit');
            $table->string('priority')->default('medium');
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();

            $table->string('item_status')->default('pending'); // pending, approved, rejected
            $table->decimal('approved_quantity', 10, 2)->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamps();

            $table->index('request_id');
            $table->index('item_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('nova_material_request_items');
        Schema::connection($this->connection)->dropIfExists('nova_material_requests');
    }
};