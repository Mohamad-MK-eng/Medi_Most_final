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
        Schema::create('clinics', function (Blueprint $table) {
    $table->id();
    $table->string('name');
  // تم حذق اوقات الفتح واوقات الاغلاق \لأنها مالها داعي الدكتور هو يلي بحدد اذا كان في دوام او لا
    $table->string('specialty')->default('General');
     $table->text('description')->nullable();
     $table->string('location')->nullable();
    $table->string('image_path')->nullable();

    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
