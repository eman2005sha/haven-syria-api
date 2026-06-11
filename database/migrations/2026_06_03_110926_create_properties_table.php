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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // عنوان العقار (مثال: شقة ديلوكس في المزة)
        $table->text('description')->nullable(); // الوصف العام
        $table->decimal('price_sp', 15, 2)->nullable(); // السعر بالليرة السورية
        $table->decimal('price_usd', 15, 2)->nullable(); // السعر بالدولار الأمريكي

        $table->string('region'); // المنطقة والحي (مثال: مشروع دمر، المزة...)
        
        // أنواع العقارات المطلوبة بالظبط
        $table->enum('property_type', ['apartment', 'villa', 'land', 'farm', 'shop', 'office']); 
        
        // نوع العرض: بيع أو إيجار
        $table->enum('offer_type', ['sale', 'rent']); 
        
        // تفاصيل الإيجار (nullable لأنها بتشتغل بس إذا كان العرض إيجار)
        $table->enum('rent_period', ['daily', 'weekly', 'monthly', 'yearly'])->nullable(); 
        
        // مفروش أو غير مفروش (true = مفروش، false = غير مفروش)
        $table->boolean('is_furnished')->default(false); 
        
        // تفاصيل داخلية للشاشة والتفاصيل
        $table->integer('area'); // المساحة بالمتر المربع
        $table->integer('rooms_count')->default(0); // عدد الغرف (0 للأراضي والمحلات)
        $table->integer('floor_number')->default(0); // رقم الطابق
        
        // حالة العقار في السوق
        $table->enum('status', ['available', 'sold', 'rented'])->default('available'); 
        
        // متابعة حالة الطلب عند الإدارة (قيد المراجعة، مقبول، مرفوض مع السبب)
        $table->enum('approval_status', ['pending', 'accepted', 'rejected'])->default('pending');
        $table->text('rejection_reason')->nullable(); 
        
        // الموقع بالتفصيل وإحداثيات الخريطة
        $table->string('address_details')->nullable(); // الموقع المكتوب نصاً
        $table->string('location_gps')->nullable(); // رابط أو إحداثيات الماب GPS
        
        // العلاقات (الربط مع صاحب العقار والمكتب العقاري المسؤول)
        $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('office_id')->nullable()->constrained('real_estate_offices')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
