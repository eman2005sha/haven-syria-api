<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    
// تابع إرسال طلب إضافة عقار (متاح لـ صاحب العقار، الشريك، والآدمن)
    public function store(Request $request)
    {
        // 1. التحقق من المدخلات بناءً على الـ Migration والـ Enums تبعك بالظبط
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_sp' => 'required_without:price_usd|numeric|min:0|nullable', // يجب إدخال سعر واحد على الأقل
            'price_usd' => 'required_without:price_sp|numeric|min:0|nullable',           
            'region' => 'required|string|max:255',
            'property_type' => 'required|in:apartment,villa,land,farm,shop,office',
            'offer_type' => 'required|in:sale,rent',
            'rent_period' => 'required_if:offer_type,rent|in:daily,weekly,monthly,yearly|nullable',
            'is_furnished' => 'required|boolean',
            'area' => 'required|integer|min:1',
            'rooms_count' => 'required|integer|min:0',
            'floor_number' => 'required|integer',
            'address_details' => 'nullable|string',
            'location_gps' => 'nullable|string',
            'office_id' => 'required|exists:real_estate_offices,id', // المكتب العقاري الموجه له الطلب
            'images' => 'required|array|min:1', // يجب رفع صورة واحدة على الأقل
            'images.*' => 'image|mimes:png,jpg,jpeg|max:2048', // شروط كل صورة (الامتداد والحجم)
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // تحديد حالة القبول تلقائياً حسب صلاحية الشخص اللي عم يرفع
        $userRole = $request->user()->role;
        $approvalStatus = ($userRole === 'admin' || $userRole === 'partner') ? 'accepted' : 'pending';
        // 2. كرتنة (إنشاء) العقار بقلب الداتابيز
        // الـ approval_status تلقائياً بياخد pending من الداتابيز، والـ status بياخد available
        $property = Property::create([
            'title' => $request->title,
            'description' => $request->description,
            'price_sp' => $request->price_sp,
            'price_usd' => $request->price_usd,
            'region' => $request->region,
            'property_type' => $request->property_type,
            'offer_type' => $request->offer_type,
            'rent_period' => $request->rent_period,
            'is_furnished' => $request->is_furnished,
            'area' => $request->area,
            'rooms_count' => $request->rooms_count,
            'floor_number' => $request->floor_number,
            'address_details' => $request->address_details,
            'location_gps' => $request->location_gps,
            'owner_id' => $request->user()->id, // أخذ ID المستخدم الحالي أوتوماتيكياً من التوكن
            'office_id' => $request->office_id,
            'approval_status' => $approvalStatus, 
        ]);

        // 3. الـ Logic السحري لرفع الصور المتعددة وتخزينها
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                // تخزين الصورة بداخل مجلد public/properties وتوليد اسم عشوائي آمن لها
                $path = $image->store('properties', 'public');

                // حفظ مسار الصورة بجدول الـ property_images وربطها بـ ID العقار الحالي
                PropertyImage::create([
                    'property_id' => $property->id,
                    'image_path' => $path,
                ]);
            }
        }

        // 4. تحميل علاقة الصور مع الرد عشان تظهر بالبوستمان فوراً
        $property->load('images');
        $message = ($approvalStatus === 'accepted') 
            ? 'تم إضافة العقار ونشره في النظام بنجاح .' 
            : 'تم إرسال طلب إضافة العقار بنجاح وهو قيد المراجعة الآن من قبل المكتب المختار.';

        return response()->json([
            'message' => $message,
            'property' => $property
        ], 201);

    }


// 1. تابع تعديل العقار الخاص بالمستخدم (متاح فقط للآدمن والبارتنر على عقاراتهم الشخصية)
    public function update(Request $request, $id)
    {
        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'العقار غير موجود'], 404);
        }

        $user = $request->user();

        // 🔒 فحص الصلاحية والخصوصية: يجب أن يكون المستخدم (آدمن أو بارتنر) وهو "صاحب العقار نفسه" الذي رفعه
        if (!in_array($user->role, ['admin', 'partner'])) {
            return response()->json(['message' => 'عذراً! هذه الصلاحية متاحة فقط لإدارة النظام والشركاء.'], 403);
        }

        if ($property->owner_id !== $user->id) {
            return response()->json(['message' => 'عذراً! لا يمكنك تعديل هذا العقار لأنه تابع لمستخدم أو شريك آخر (قيد الخصوصية).'], 403);
        }

        // التحقق من المدخلات
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price_sp' => 'sometimes|numeric|min:0|nullable',
            'price_usd' => 'sometimes|numeric|min:0|nullable',
            'region' => 'sometimes|string|max:255',
            'property_type' => 'sometimes|in:apartment,villa,land,farm,shop,office',
            'offer_type' => 'sometimes|in:sale,rent',
            'rent_period' => 'required_if:offer_type,rent|in:daily,weekly,monthly,yearly|nullable',
            'is_furnished' => 'sometimes|boolean',
            'area' => 'sometimes|integer|min:1',
            'rooms_count' => 'sometimes|integer|min:0',
            'floor_number' => 'sometimes|integer',
            'address_details' => 'nullable|string',
            'location_gps' => 'nullable|string',
            'status' => 'sometimes|in:available,sold,rented', // تحديث حالة العقار في السوق
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // تحديث البيانات
        $property->update($request->only([
            'title', 'description', 'price_sp', 'price_usd', 'region', 
            'property_type', 'offer_type', 'rent_period', 'is_furnished', 
            'area', 'rooms_count', 'floor_number', 'address_details', 'location_gps', 'status'
        ]));

        return response()->json([
            'message' => 'تم تحديث بيانات عقارك الخاص بنجاح.',
            'property' => $property->load('images')
        ], 200);
    }

    // 2. تابع حذف العقار الشخصي مع صوره (متاح فقط للآدمن والبارتنر على عقاراتهم الشخصية)
    public function destroy(Request $request, $id)
    {
        $property = Property::with('images')->find($id);

        if (!$property) {
            return response()->json(['message' => 'العقار غير موجود'], 404);
        }

        $user = $request->user();

        // 🔒 فحص الصلاحية والخصوصية التامة للحذف
        if (!in_array($user->role, ['admin', 'partner'])) {
            return response()->json(['message' => 'عذراً! هذه الصلاحية متاحة فقط لإدارة النظام والشركاء.'], 403);
        }

        if ($property->owner_id !== $user->id) {
            return response()->json(['message' => 'عذراً! لا يمكنك حذف هذا العقار لأنه تابع لمستخدم أو شريك آخر.'], 403);
        }

        // مسح ملفات الصور حقيقةً من الـ Storage
        foreach ($property->images as $image) {
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }

        // حذف العقار من قاعدة البيانات
        $property->delete();

        return response()->json([
            'message' => 'تم حذف عقارك الخاص وكافة صوره من النظام بنجاح.'
        ], 200);
    }

    }


