<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::connection('vtiger')->create('vtiger_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email', 100)->index();
            $table->string('token', 255)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('used')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('vtiger')->dropIfExists('vtiger_password_resets');
    }
};
