<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PropertyImage extends Model
{
    protected $fillable = ['property_id', 'image_path'];

    protected $appends = ['image_url'];

    // علاقة عكسية: الصورة تتبع لعقار معين
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    // رابط الصورة الكامل (مطلق) لاستخدامه مباشرة بالـ Frontend
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
