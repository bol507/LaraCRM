<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'vtiger';

    public function up(): void
    {
        // Vendor Quotes (RFQ Responses)
        Schema::connection($this->connection)->create('nova_vendor_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('project_id');
            $table->unsignedBigInteger('material_request_id');
            $table->unsignedBigInteger('vendor_id'); // vtiger_vendor.vendorid
            $table->string('quote_number')->unique(); // CF-2026-0001
            $table->enum('status', ['draft', 'sent', 'submitted', 'negotiated', 'accepted', 'processed', 'rejected'])->default('draft');
            $table->date('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('assigned_to')->nullable(); // Responsible buyer
            $table->unsignedInteger('accepted_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index(['status', 'material_request_id']);
            $table->index(['status', 'accepted_at']);
        });

        // Quote Items
        Schema::connection($this->connection)->create('nova_vendor_quote_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_quote_id')->constrained('nova_vendor_quotes')->onDelete('cascade');
            $table->unsignedBigInteger('material_request_item_id');
            $table->string('item_name', 255)->nullable();
            $table->string('catalog_item_type')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->date('delivery_date')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index('vendor_quote_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('nova_vendor_quote_items');
        Schema::connection($this->connection)->dropIfExists('nova_vendor_quotes');
    }
};
