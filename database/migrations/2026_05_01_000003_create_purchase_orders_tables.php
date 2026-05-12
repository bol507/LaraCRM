<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'vtiger';

    public function up(): void
    {
        Schema::connection($this->connection)->create('nova_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('project_id');
            $table->string('po_number')->unique(); // Example: PO-2026-0001
            $table->unsignedInteger('vendor_quote_id')->nullable(); // Link to accepted quote
            $table->unsignedInteger('material_request_id')->nullable(); // Link to source request
            $table->unsignedInteger('vendor_id');

            // PO Statuses
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'partially_received',
                'fully_received',
                'cancelled'
            ])->default('draft');

            // Totals (denormalized for performance)
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Key dates
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            // Terms and notes
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Audit
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Indexes for frequent queries
            $table->index(['project_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_quote_id']);
            $table->index(['material_request_id']);
        });

        Schema::connection($this->connection)->create('nova_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            
            // Link to source items for traceability
            $table->unsignedInteger('vendor_quote_item_id')->nullable();
            $table->unsignedInteger('material_request_item_id')->nullable();
            
            // Item data (copied from quote for immutable history)
            $table->string('item_name');
            $table->string('catalog_item_type')->nullable();
            $table->string('unit');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2); // Actual price from accepted quote
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 12, 2); // quantity * unit_price * (1 - discount/100)
            
            // Delivery and item-specific terms
            $table->date('expected_delivery_date')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            
            // Receipt status (for partial/complete tracking)
            $table->decimal('received_quantity', 10, 2)->default(0);
            $table->enum('receipt_status', ['pending', 'partial', 'complete'])->default('pending');
            
            $table->timestamps();
            
            $table->foreign('purchase_order_id')
                ->references('id')->on('nova_purchase_orders')
                ->onDelete('cascade');
            $table->index(['purchase_order_id', 'receipt_status']);
        });
        
        Schema::connection($this->connection)->create('nova_purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('po_item_id');
            $table->decimal('quantity_received', 10, 2);
            $table->date('received_date');
            $table->string('received_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('purchase_order_id')
                ->references('id')->on('nova_purchase_orders')
                ->onDelete('cascade');
            $table->foreign('po_item_id')
                ->references('id')->on('nova_purchase_order_items')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('nova_purchase_order_receipts');
        Schema::connection($this->connection)->dropIfExists('nova_purchase_order_items');
        Schema::connection($this->connection)->dropIfExists('nova_purchase_orders');
    }
};