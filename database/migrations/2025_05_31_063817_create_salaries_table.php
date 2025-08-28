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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secretary_id')->constrained('secretaries')->onDelete('cascade');
            $table->foreignId('salary_setting_id')->constrained('salary_settings')->onDelete('restrict');
            $table->decimal('base_amount', 10, 2)->nullable(false);
            $table->decimal('bonus_amount', 10, 2)->nullable(false);
            $table->decimal('total_amount', 10, 2)->nullable(false);
            $table->enum('status', ['pending', 'paid', 'cancelled']);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
