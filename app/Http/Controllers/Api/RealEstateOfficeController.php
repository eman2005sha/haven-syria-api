<?php

namespace App\Http\Controllers\Api;
use App\Models\RealEstateOffice;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RealEstateOfficeController extends Controller
{
    
// 1. عرض قائمة كاملة بجميع المكاتب الشريكة داخل النظام (متاحة للمدير والزبائن)
    public function index()
    {
        // منجيب كل المكاتب الشريكة من الداتابيز
        $offices = RealEstateOffice::all();

        return response()->json([
            'message' => 'تم جلب قائمة المكاتب الشريكة بنجاح',
            'offices' => $offices
        ], 200);
    }

    // 2. إضافة مكتب شريك جديد (خاصة بالسوبر آدمن فقط)
    public function store(Request $request)
    {
        // التحقق من المدخلات
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string',
            'phone' => 'nullable|string',
            'description' => 'nullable|string', // وصف خدمات المكتب الشريك
            'manager_id' => 'nullable|exists:users,id', // التأكد أن الآي دي للمدير موجود بجدول الـ users
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048', // شروط اللوغو
            ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // رفع وتخزين اللوغو إذا وجد
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('offices', 'public');
        }
        // فحص ذكي: إذا السوبر آدمن حدد مدير للمكتب، نتأكد أن الـ role تبعه إما admin أو partner
        if ($request->manager_id) {
            $manager = User::find($request->manager_id);
            if (!in_array($manager->role, ['admin', 'partner'])) {
                return response()->json([
                    'message' => 'خطأ! المستخدم المختار يجب أن يكون شريك (partner) أو مدير (admin) ليتم تعيينه مسؤلاً عن هاد المكتب الشريك.'
                ], 400);
            }
        }

        // إنشاء المكتب الشريك بقلب قاعدة البيانات
        $office = RealEstateOffice::create([
            'name' => $request->name,
            'location' => $request->location,
            'phone' => $request->phone,
            'description' => $request->description,
            'manager_id' => $request->manager_id,
            'logo_path' => $logoPath, // حفظ المسار بالداتابيز
        ]);

        return response()->json([
            'message' => 'تم إضافة المكتب العقاري الشريك بنجاح إلى Haven Syria',
            'office' => $office
        ], 201);
    }

    // 3. حذف مكتب شريك من النظام (خاصة بالسوبر آدمن فقط)
    public function destroy($id)
    {
        $office = RealEstateOffice::find($id);

        if (!$office) {
            return response()->json(['message' => 'المكتب الشريك غير موجود أصلاً'], 404);
        }

        $office->delete();

        return response()->json([
            'message' => 'تم حذف المكتب الشريك من النظام بنجاح'
        ], 200);
    }
}
