<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('medical_center_wallet_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('medical_wallet_id')->constrained('medical_center_wallets')->onDelete('cascade');
    $table->foreignId('clinic_id')->constrained('clinics')->onDelete('cascade');
    $table->decimal('amount', 10, 2);
    $table->string('type');
    $table->string('reference')->nullable();
    $table->decimal('balance_before', 10, 2);
    $table->decimal('balance_after', 10, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_center_wallet_transactions');
    }
};
