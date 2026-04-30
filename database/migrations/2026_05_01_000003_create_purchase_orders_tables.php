<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique(); // OC-2026-0042
            $table->unsignedInteger('project_id');
            $table->unsignedBigInteger('vendor_id')->nullable(); // ⚠️ Ajusta tipo si tu tabla vendors usa int
            $table->string('status')->default('draft'); // draft, approved, sent_to_vendor, partially_received, completed, closed
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->date('order_date');
            $table->date('expected_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_id');
            $table->foreign('po_id')->references('id')->on('purchase_orders')->onDelete('cascade');

            $table->unsignedBigInteger('source_request_item_id')->nullable();
            $table->foreign('source_request_item_id')->references('id')->on('material_request_items')->onDelete('set null');

            $table->string('item_name');
            $table->decimal('quantity_ordered', 10, 2);
            $table->decimal('quantity_received', 10, 2)->default(0);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 12, 2);
            $table->text('vendor_notes')->nullable();
            $table->timestamps();

            $table->index('po_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};