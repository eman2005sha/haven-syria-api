<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RealEstateOfficeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PropertyController;

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

   //  لوحة تحكم مراجعة العقارات (خاصة فقط بالآدمن والشريك)
    Route::get('/properties/pending-requests', [PropertyController::class, 'getPendingRequests']);//عرض الطلبات المعلقة
    Route::put('/properties/{id}/review', [PropertyController::class, 'reviewRequest']);//مراجعة الطلب(قبول-رفض)
    
    Route::middleware('role:admin,partner,owner')->group(function () {
    Route::post('/properties', [PropertyController::class, 'store']);//اضافة عقار
    Route::put('/properties/{id}', [PropertyController::class, 'update']);   // تعديل
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']); // حذف
 
  });
  
    // 2. روابط التحكم بالمكاتب الشريكة (محمية بالتوكن + شرط صلاحية الآدمن السوبر فقط!)
    Route::middleware('role:admin')->group(function () {
        Route::post('/offices', [RealEstateOfficeController::class, 'store']); // إضافة مكتب شريك
        Route::delete('/offices/{id}', [RealEstateOfficeController::class, 'destroy']); // حذف مكتب شريك
    });

});