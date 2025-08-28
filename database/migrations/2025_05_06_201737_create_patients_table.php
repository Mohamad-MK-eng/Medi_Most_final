
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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->string('wallet_pin', 60)->nullable(); // Hashed PIN
            $table->timestamp('wallet_activated_at')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('blood_type')->nullable();
            $table->text('chronic_conditions')->nullable(); // JSON array of conditions
            $table->string('insurance_provider')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance', 'wallet_pin', 'wallet_activated_at']);
        });
    }
};
