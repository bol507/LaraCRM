<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    


    public function up(): void
    {
        Schema::connection($this->connection)->create('procurement_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);   // 'item_type', 'reason_type', 'unit', 'priority'
            $table->string('code', 50);       // 'material', 'missing', 'kg', 'urgent', etc.
            $table->string('label', 100);     // 'Material', 'Falta', 'Kilogramo', 'Urgente', etc.
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category', 'code']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('procurement_catalogs');
    }
};