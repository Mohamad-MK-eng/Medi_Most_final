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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('doctor_id')->constrained('doctors')->onDelete('cascade');
            $table->foreignId('clinic_id')->constrained('clinics')->onDelete('cascade');
$table->foreignId('time_slot_id')->references('id')->on('time_slots')->onDelete('cascade');

            $table->datetime('appointment_date')->nullable(false);
            $table->enum('status', ['pending', 'confirmed','absent', 'cancelled','completed']);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('fee',8,2)->nullable();
$table->string('reason')->nullable();
$table->date('cancelled_at')->nullable();
$table->date(column: 'previous_date')->nullable();
            $table->text('notes')->nullable();
                        $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }




    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
