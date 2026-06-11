<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'title', 
        'description', 
        'price_sp', 
        'price_usd',
        'region', 
        'property_type', 
        'offer_type', 
        'rent_period', 
        'is_furnished', 
        'area', 
        'rooms_count', 
        'floor_number', 
        'status', 
        'approval_status', 
        'rejection_reason', 
        'address_details', 
        'location_gps', 
        'owner_id', 
        'office_id'
    ];
    // تحويل أنواع البيانات عند الإرجاع بطلب الـ API
    protected $casts = [
        'price_sp' => 'float',
        'price_usd' => 'float',
        'is_furnished' => 'boolean',
        'area' => 'integer',
        'rooms_count' => 'integer',
        'floor_number' => 'integer',
    ];
    // علاقة العقار مع الصور (العقار الواحد له عدة صور)
    public function images()
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }

    // علاقة العقار مع صاحبه (اليوزر اللي رفعه)
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // علاقة العقار مع المكتب العقاري الموجه له
    public function office()
    {
        return $this->belongsTo(RealEstateOffice::class, 'office_id');
    }

}
