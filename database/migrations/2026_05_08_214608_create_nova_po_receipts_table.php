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
        Schema::connection($this->connection)->create('nova_po_receipts', function (Blueprint $table) {
            $table->id();
            
           
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('po_item_id');
            
            // Datos de la recepción
            $table->decimal('quantity_received', 10, 2);
            $table->date('received_date');
            $table->unsignedInteger('received_by')->nullable(); // Usuario que registró
            $table->text('notes')->nullable(); // Notas de la recepción
            
            // Auditoría
            $table->timestamps();
            
            // Índices para consultas frecuentes
            $table->index(['purchase_order_id', 'received_date']);
            $table->index(['po_item_id']);
            $table->index(['received_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('nova_po_receipts');
    }
};
