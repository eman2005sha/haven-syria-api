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
        ];
    }

    
public function routeNotificationForFirebase()
{
    return $this->fcm_token;
}
}
