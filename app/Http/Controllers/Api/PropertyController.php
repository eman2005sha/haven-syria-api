<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Notifications\PropertyRequestStatusNotification;
class PropertyController extends Controller
{
    // 1. تابع إضافة عقار جديد
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_sp' => 'required_without:price_usd|numeric|min:0|nullable', 
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
            'office_id' => 'required|exists:real_estate_offices,id', 
            'images' => 'required|array|min:1', 
            'images.*' => 'image|mimes:png,jpg,jpeg|max:2048', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        // 🕵️‍♂️ جلب المكتب الذي يديره المستخدم الحالي (آدمن أو شريك)
        $myOffice = \DB::table('real_estate_offices')->where('manager_id', $user->id)->first();

        // الحالة الافتراضية لأي مستخدم أو صاحب عقار عادي هي قيد الانتظار
        $approvalStatus = 'pending';

        // 🌟 الشرط الذكي: إذا كان المستخدم (آدمن أو شريك) وعم يرفع العقار "لمكتبه هو بالذات"، بياخد مقبول فوراً
        if (in_array($user->role, ['admin', 'partner']) && $myOffice && $myOffice->id == $request->office_id) {
            $approvalStatus = 'accepted';
        }

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
            'owner_id' => $user->id, 
            'office_id' => $request->office_id,
            'approval_status' => $approvalStatus, 
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('properties', 'public');
                PropertyImage::create([
                    'property_id' => $property->id,
                    'image_path' => $path,
                ]);
            }
        }

        $property->load('images');
        
        $message = ($approvalStatus === 'accepted') 
            ? 'تم إضافة العقار ونشره في مكتبك بنجاح تلقائي.' 
            : 'تم إرسال طلب إضافة العقار بنجاح وهو قيد المراجعة الآن من قبل المكتب المختار.';

        return response()->json(['message' => $message, 'property' => $property], 201);
    }

    // 2. تابع تعديل العقار
    public function update(Request $request, $id)
    {
        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'العقار غير موجود'], 404);
        }

        $user = $request->user();

        if (!in_array($user->role, ['admin', 'partner'])) {
            return response()->json(['message' => 'عذراً! هذه الصلاحية متاحة فقط لإدارة النظام والشركاء.'], 403);
        }

        // جلب المكتب الذي يديره المستخدم الحالي
        $office = \DB::table('real_estate_offices')->where('manager_id', $user->id)->first();

        // 🔒 قفل الأمان والخصوصية الجديد حسب طلبك:
        $isOwner = ($property->owner_id === $user->id); // هل هو صاحب العقار؟
        $isOfficeManagerAndAccepted = ($office && $property->office_id === $office->id && $property->approval_status === 'accepted'); // هل هو مدير المكتب والعقار مقبول؟

        if (!$isOwner && !$isOfficeManagerAndAccepted) {
            return response()->json(['message' => 'عذراً! لا يمكنك تعديل هذا العقار إلا إذا كنت صاحبه أو أنه معروض ومقبول في مكتبك.'], 403);
        }

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
            'status' => 'sometimes|in:available,sold,rented', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property->update($request->only([
            'title', 'description', 'price_sp', 'price_usd', 'region', 
            'property_type', 'offer_type', 'rent_period', 'is_furnished', 
            'area', 'rooms_count', 'floor_number', 'address_details', 'location_gps', 'status'
        ]));

        return response()->json([
            'message' => 'تم تحديث بيانات العقار بنجاح.',
            'property' => $property->load('images')
        ], 200);
    }

    // 3. تابع حذف العقار
    public function destroy(Request $request, $id)
    {
        $property = Property::with('images')->find($id);

        if (!$property) {
            return response()->json(['message' => 'العقار غير موجود'], 404);
        }

        $user = $request->user();

        if (!in_array($user->role, ['admin', 'partner'])) {
            return response()->json(['message' => 'عذراً! هذه الصلاحية متاحة فقط لإدارة النظام والشركاء.'], 403);
        }

        $office = \DB::table('real_estate_offices')->where('manager_id', $user->id)->first();

        // 🔒 قفل الحذف حسب فكرتك بالظبط:
        $isOwner = ($property->owner_id === $user->id); // حذف عقاره الشخصي
        $isOfficeManagerAndAccepted = ($office && $property->office_id === $office->id && $property->approval_status === 'accepted'); // حذف عقار وافق عليه بمكتبه

        if (!$isOwner && !$isOfficeManagerAndAccepted) {
            return response()->json(['message' => 'عذراً! لا يمكنك حذف هذا العقار إلا إذا كنت صاحبه أو قمت بقبوله مسبقاً في مكتبك.'], 403);
        }

        // حذف الصور حقيقة من السيرفر
        foreach ($property->images as $image) {
            if (Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }

        $property->delete();

        return response()->json(['message' => 'تم حذف العقار وكافة صوره من النظام بنجاح.'], 200);
    }

    // 4. تابع جلب الطلبات المعلقة لمكتب المستخدم الحالي
    public function getPendingRequests(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'partner'])) {
            return response()->json(['message' => 'عذراً! هذه الصلاحية غير متاحة لك.'], 403);
        }

        $office = \DB::table('real_estate_offices')->where('manager_id', $user->id)->first();

        if (!$office) {
            return response()->json(['message' => 'عذراً! حسابك غير مرتبط بإدارة أي مكتب عقاري حالياً.'], 404);
        }

        $pendingProperties = Property::with(['images', 'owner'])
            ->where('approval_status', 'pending')
            ->where('office_id', $office->id) 
            ->latest()
            ->get();

        return response()->json(['properties' => $pendingProperties], 200);
    }

    // 5. تابع مراجعة الطلب (قبول أو رفض)
    public function reviewRequest(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'partner'])) {
            return response()->json(['message' => 'عذراً! هذه الصلاحية غير متاحة لك.'], 403);
        }

        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'العقار غير موجود'], 404);
        }

        $office = \DB::table('real_estate_offices')->where('manager_id', $user->id)->first();

        if (!$office || $property->office_id !== $office->id) {
            return response()->json(['message' => 'عذراً! لا يمكنك مراجعة هذا العقار لأنه موجه لمكتب عقاري آخر.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'approval_status' => 'required|in:accepted,rejected',
            'rejection_reason' => 'required_if:approval_status,rejected|string|nullable|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property->update([
            'approval_status' => $request->approval_status,
            'rejection_reason' => $request->approval_status === 'rejected' ? $request->rejection_reason : null,
        ]);

        

// 🔔 إرسال إشعار لصاحب العقار
$property->owner->notify(new PropertyRequestStatusNotification($property));


        $message = $request->approval_status === 'accepted' 
            ? 'تم قبول العقار بنجاح ونشره في النظام.' 
            : 'تم رفض العقار وتسجيل سبب الرفض بنجاح.';

        return response()->json(['message' => $message, 'property' => $property->load('images')], 200);
    }



    // تابع يتيح لمالك العقار رؤية عقاراته الشخصية فقط لمتابعة حالتها (pending, accepted, rejected)
public function getMyProperties(Request $request)
{
    $user = $request->user();

    // جلب العقارات التي يملكها هذا المستخدم الحالي بالظبط مع صورها
    $myProperties = Property::with('images')
        ->where('owner_id', $user->id)
        ->latest()
        ->get();

    return response()->json([
        'message' => 'تم جلب عقاراتك الشخصية بنجاح.',
        'properties' => $myProperties
    ], 200);
}

    
//تابع تحديث حالة العقار متاح/ مؤجر /مباع   متاح للمدير وللبارتنر
     public function updateStatus(Request $request, $id)
    {
        $property = Property::findOrFail($id);
          if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'العقار غير موجود'
            ], 404);
        }
        $request->validate(['status' => 'required|in:available,sold,rented']);
        $property->update(['status' => $request->status]);
       return response()->json([
            'success' => true,
            'data' => $property,
            'message' => 'تم تحديث حالة العقار بنجاح'
        ], 200);
    }

  


}