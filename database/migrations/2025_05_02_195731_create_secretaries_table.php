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
        Schema::create('secretaries', function (Blueprint $table) {
            $table->id();
            // هون لازم يتعدل قصة salary_id
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('salary', 10, 2)->nullable(); // ✅ Add this
            $table->json('workdays')->nullable(false); // Regular working hours
            $table->json('emergency_absences')->nullable(); // Track unexpected absences
            $table->json('performance_metrics')->nullable(); // Track performance data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secretaries');
    }
};
