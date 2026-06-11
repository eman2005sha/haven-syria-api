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
        Schema::create('real_estate_offices', function (Blueprint $table) {
            $table->id();
    $table->string('name'); // اسم المكتب
    $table->string('location')->nullable(); // الموقع العام
    $table->string('phone')->nullable(); // رقم المكتب للتواصل
    $table->text('description')->nullable(); // وصف الخدمات (خاص بالمتاجر والشركاء)
    $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
    $table->string('logo_path')->nullable(); // 
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('real_estate_offices');
    }
};
