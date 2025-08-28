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
        Schema::create('salary_settings', function (Blueprint $table) {

            $table->id();
            // هون سويتها nullable لان مابعرف شو قصدك فيها
            $table->decimal('per_patient_bonus', 10, 2)->nullable();
            $table->timestamps();
            // حذفت speciality_id
          });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_settings');
    }
};
