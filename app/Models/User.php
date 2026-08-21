<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // تفعيل ميزة التوكنز لتطبيقات الموبايل

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */    
use HasApiTokens, HasFactory, Notifiable;

    // الحقول المسموح بتخزينها تلقائياً بطلب واحد
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'role',
        'password',
        'is_active',
       'email_verified_at', // <-- أضيفي هذا السطر هنا
    ];
   protected $hidden = [
        'password',
        'remember_token',
          'created_at',
        'updated_at',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // المكتب الذي يديره هذا المستخدم (إن وُجد) — لعرضه بجدول إدارة المستخدمين
    public function managedOffice()
    {
        return $this->hasOne(RealEstateOffice::class, 'manager_id');
    }

    
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
    
     // العقارات المحفوظة
     
    public function favoriteProperties()
    {
        return $this->belongsToMany(Property::class, 'favorites');
    }
}
