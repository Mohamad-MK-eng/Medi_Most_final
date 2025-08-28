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
        Schema::create('clinic_wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_wallet_id')->constrained()->onDelete('cascade');
            $table->decimal('amount',10,2);
            $table->string('type');  // like payment or refund or withdraw ...
            $table->string('reference')->nullable();
            $table->decimal('balance_before',10,2);
            $table->decimal('balance_after' ,10,2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_wallet_transactions');
    }
};
