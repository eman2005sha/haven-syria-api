<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    protected $fillable = ['property_id', 'image_path'];

    // علاقة عكسية: الصورة تتبع لعقار معين
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
