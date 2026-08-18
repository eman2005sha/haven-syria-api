<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\EmailVerification;
use App\Mail\SendOtpMail;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AuthController extends Controller
{
    // 1. تابع إنشاء حساب جديد (Register)
    public function register(Request $request)
    {
  $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|min:10|max:15', // ثابتة حسب جدولك
            'role' => 'required|in:admin,partner,owner,customer', 
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        // 1. توليد كود عشوائي من 6 أرقام
        $otpCode = rand(100000, 999999);

        // 2. حفظ الكود بالداتابيز لتأكيده لاحقاً
        EmailVerification::create([
            'email' => $user->email,
            'code' => $otpCode,
            'expired_at' => Carbon::now()->addMinutes(10),
        ]);

        // 3. إرسال الإيميل أوتوماتيكياً عبر Mailtrap
        Mail::to($user->email)->send(new SendOtpMail($otpCode));

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح. يرجى التحقق من بريدك الإلكتروني لإدخال رمز التحقق (OTP).'
        ], 201);
    }

    // 2. تابع تسجيل الدخول (Login)
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // البحث عن المستخدم بالإيميل
        $user = User::where('email', $request->email)->first();

        // التأكد من صحة البيانات ومطابقة الباسورد المشفر
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الاعتماد غير صحيحة، تأكد من الإيميل أو الباسورد'
            ], 401);
        }

        // 2. الشرط الإضافي المهم: منع الدخول إذا لم يتم تأكيد الحساب بالـ OTP
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'يرجى تأكيد حسابك عبر أدخال رمز التحقق (OTP) المرتقب ببريدك الإلكتروني أولاً.'
            ], 403);
        }

        
        if (!$user->is_active) {
            return response()->json([
                'message' => 'تم تعطيل هذا الحساب من قبل إدارة النظام، يرجى التواصل مع الدعم'
            ], 403);
        }

        // توليد توكن جديد له بـ عملية الدخول
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user 
        ], 200);
    }

    // 3. تابع تسجيل الخروج (Logout) وتدمير التوكن
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح وتأمين الحساب'
        ], 200);
    }

    // 4. عرض بيانات البروفايل للمستخدم الحالي
    public function profile(Request $request)
    {
        // لارافيل بجيب بيانات المستخدم الحالي أوتوماتيكياً من التوكن الممرر
        $user = $request->user();

        return response()->json([
            'message' => 'تم جلب بيانات البروفايل بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
            ]
        ], 200);
    }
    // 5. تعديل بيانات البروفايل (الاسم، الهاتف، والإيميل)
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // التحقق من البيانات الجديدة مع استثناء إيميل المستخدم الحالي من شرط الـ unique
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|min:10|max:15',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // تحديث البيانات بقلب الداتابيز
        $user->update([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'تم تحديث بيانات البروفايل بنجاح  ',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
            ]
        ], 200);
}
//6. تابع تأكيد الإيميل عبر الكود الرقمي
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // البحث عن الكود ومطابقته
        $check = EmailVerification::where('email', $request->email)
                                    ->where('code', $request->code)
                                    ->first();

        if (!$check) {
            return response()->json(['message' => 'رمز التحقق غير صحيح'], 400);
        }

        // التأكد من الوقت
        if (Carbon::parse($check->expired_at)->isPast()) {
            return response()->json(['message' => 'رمز التحقق انتهت صلاحيته'], 400);
        }

        // تفعيل اليوزر بجدول قاعدة البيانات
        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = Carbon::now();
        $user->save();

        // حذف الكود للأمان
        $check->delete();

        // توليد التوكن الرسمي للدخول هلق
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تفعيل الحساب بنجاح، أهلاً بك في Haven Syria  ',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $user->role,
            ]
        ], 200);
    }
    // 7. تابع طلب كود نسيان كلمة المرور (Forgot Password)
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا في النظام'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // توليد كود OTP جديد من 6 أرقام للـ Reset
        $otpCode = rand(100000, 999999);

        // حفظ الكود بجدول الـ email_verifications (فينا نستخدم نفس الجدول للأمان والاختصار)
        // بنعمل تحديث أو إنشاء جديد إذا كان في كود قديم
        EmailVerification::updateOrCreate(
            ['email' => $request->email],
            [
                'code' => $otpCode,
                'expired_at' => Carbon::now()->addMinutes(10)
            ]
        );

        // إرسال الإيميل
        Mail::to($request->email)->send(new ResetPasswordMail($otpCode));

        return response()->json([
            'message' => 'تم إرسال رمز إعادة تعيين كلمة المرور إلى بريدك الإلكتروني بنجاح.'
        ], 200);
    }

    // 2. تابع إدخال الكود وتغيير الباسورد الفعلي (Reset Password)
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed', // حقل التثبيت password_confirmation
        ], [
            'password.confirmed' => 'كلمة المرور الجديدة غير متطابقة مع حقل التثبيت'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // التحقق من صحة الكود بجدول الـ OTP
        $check = EmailVerification::where('email', $request->email)
                                    ->where('code', $request->code)
                                    ->first();

        if (!$check) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو غير متطابق'], 400);
        }

        // التأكد من الوقت والصلاحية
        if (Carbon::parse($check->expired_at)->isPast()) {
            return response()->json(['message' => 'رمز التحقق انتهت صلاحيته'], 400);
        }

        // تحديث كلمة المرور للمستخدم وتشفيرها
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // حذف الكود لعدم استخدامه مرة تانية
        $check->delete();

        return response()->json([
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح! يمكنك الآن تسجيل الدخول بالبيانات الجديدة.'
        ], 200);
    }

}