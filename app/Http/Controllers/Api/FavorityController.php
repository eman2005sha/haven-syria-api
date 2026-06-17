<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavorityController extends Controller
{
    
    // عرض العقارات المحفوظة (مع صورها)
    public function index()
    {
        $user = auth()->user();

        // 🌟 تحسين ناري: جلبنا العقار وجلبنا "الصور التابعة للعقار" بنفس الوقت (Nested Eager Loading) كرمال الـ Frontend
        $favorites = Favorite::with(['property.images'])
            ->where('user_id', $user->id)
            ->latest() // ترتيب من الأحدث للأقدم
            ->get();

        return response()->json([
            'success' => true,
            'count' => $favorites->count(),
            'data' => $favorites,
        ]);
    }

    // إضافة عقار إلى المحفوظات
    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
        ]);

        $user = auth()->id();
        $propertyId = $request->property_id;

        // 🔒 قفل الأمان لطلب الستيك هولدر: التأكد أن العقار مقبول ومنشور ومتاح بالسيستم أولاً!
        $property = Property::find($propertyId);
        if ($property->approval_status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'عذراً! لا يمكنك إضافة هذا العقار للمفضلة لأنه لم يتم قبوله ونشره من قبل إدارة المكتب بعد.',
            ], 403);
        }

        // التحقق إذا كان العقار محفوظاً مسبقاً
        $exists = Favorite::where('user_id', $user)
            ->where('property_id', $propertyId)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'العقار محفوظ مسبقاً في مفضلتك بالفعل.',
            ], 400);
        }

        // إضافة إلى المحفوظات
        $favorite = Favorite::create([
            'user_id' => $user,
            'property_id' => $propertyId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ العقار في المفضلة بنجاح.',
            'data' => $favorite->load('property.images'), // نرجعه مع الصور ليتحدث بالواجهة فوراً
        ]);
    }

    // حذف عقار من المحفوظات
    public function destroy($propertyId)
    {
        $user = auth()->id();

        $favorite = Favorite::where('user_id', $user)
            ->where('property_id', $propertyId)
            ->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'message' => 'هذا العقار غير موجود في محفوظاتك أصلاً.',
            ], 404);
        }

        $favorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم إزالة العقار من المحفوظات بنجاح.',
        ]);
    }
}