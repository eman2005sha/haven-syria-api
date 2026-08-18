<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RealEstateOffice extends Model
{

use HasFactory;

    // اسم الجدول في قاعدة البيانات
    protected $table = 'real_estate_offices';

    // الأعمدة المسموح بتعبئتها جماعياً وحفظها بأمان
    protected $fillable = [
        'name',
        'location',
        'phone',
        'description',
        'manager_id',
        'logo_path'
    ];
    protected $casts = [
    'manager_id' => 'integer',
];

    protected $appends = ['logo_url'];

    /**
     * علاقة المكتب مع المستخدم (المدير المسؤول)
     * كل مكتب ينتمي لمستخدم واحد (مدير أو شريك)
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // رابط اللوغو الكامل (مطلق) لاستخدامه مباشرة بالـ Frontend
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}






