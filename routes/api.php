<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RealEstateOfficeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\OwnerNotificationController;
use App\Http\Controllers\Api\PublicPropertyController;
use App\Http\Controllers\Api\FavorityController;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// روابط عامة ما بتحتاج توكن (متاحة للكل)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);



    // روابط محمية (لازم يكون مع اليوزر Token بالـ Header وإلا بيرفضه السيرفر)
    Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // روابط البروفايل   
    Route::get('/profile', [AuthController::class, 'profile']);          // عرض البروفايل
    Route::put('/profile', [AuthController::class, 'updateProfile']);    // تعديل البروفايل (استخدمنا PUT للتحديث)
    //  عرض المكاتب الشريكة متاح لجميع المستخدمين المسجلين بالنظام
    Route::get('/offices', [RealEstateOfficeController::class, 'index']);
    
    
    Route::get('/property/{officeId}', [PublicPropertyController::class, 'index']);//عرض عقارات مكتب معين(تصفح العقارات)
    Route::get('/detailes/{id}', [PublicPropertyController::class, 'show']);//عرض تفاصيل عقار معين
    Route::get('/properties/browse', [PublicPropertyController::class, 'browse']);// ( فلترة (سعر، منطقة، نوع
    Route::get('/favorites', [FavorityController::class, 'index']);              // عرض المحفوظات
    Route::post('/favorites', [FavorityController::class, 'store']);             // حفظ عقار
    Route::delete('/favorites/{propertyId}', [FavorityController::class, 'destroy']); // حذف من المحفوظات
   
    //  لوحة تحكم مراجعة العقارات (خاصة فقط بالآدمن والشريك)
    Route::get('/properties/pending-requests', [PropertyController::class, 'getPendingRequests']);//عرض الطلبات المعلقة
    Route::put('/properties/{id}/review', [PropertyController::class, 'reviewRequest']);//مراجعة الطلب(قبول-رفض)
   // تحديث حالة العقار وضبط الإعدادات خاصة بالادمن والشريك
    Route::patch('properties/{id}/status', [PropertyController::class, 'updateStatus']);//تحديث حالة العقار
    Route::post('settings', [SettingController::class, 'update']);//تحديث إعدادات النظام للادمن وإعدادات المكتب للشريك
    Route::get('settings', [SettingController::class, 'index']);//عرض إعدادات النظام للادمن وإعدادات المكتب للشريك
     Route::put('/properties/{id}', [PropertyController::class, 'update']); //تعديل عقار
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']); //حذف عقار 
   
    Route::middleware('role:admin,partner,owner')->group(function () {
    Route::post('/properties', [PropertyController::class, 'store']);//اضافة عقار
    Route::get('/getproperties', [PropertyController::class, 'getMyProperties']);//عرض عقارات الشخصية لصاحب العقار لمتابعة حالاتها
   Route::get('/notifications', [OwnerNotificationController::class, 'index']);//جلب جميع الإشعارات لصاحب العقار(إشعارات تحديث حالة الطلب )
    Route::put('/notifications/{notificationId}/read', [OwnerNotificationController::class, 'markAsRead']);// تحديث إشعار كمقروء
    Route::put('/notifications/read-all', [OwnerNotificationController::class, 'markAllAsRead']); // تحديث الكل كمقروء
    
    });
  
    // 2. روابط التحكم بالمكاتب الشريكة (محمية بالتوكن + شرط صلاحية الآدمن السوبر فقط!)
    Route::middleware('role:admin')->group(function () {
    Route::post('/offices', [RealEstateOfficeController::class, 'store']); // إضافة مكتب شريك
    Route::delete('/offices/{id}', [RealEstateOfficeController::class, 'destroy']); // حذف مكتب شريك
    });


    
  


});