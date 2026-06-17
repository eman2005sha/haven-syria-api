<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Models\RealEstateOffice;
class SettingController extends Controller
{
  // عرض إعدادات النظام للآدمن (العامة) أو إعدادات المكتب للشريك
public function index(Request $request)
{
    $user = $request->user();

    // 1. إذا كان المستخدم سوبر آدمن -> يرى إعدادات النظام العامة من الـ Cache
    if ($user->role === 'admin') {
        $globalSettings = [
            'type' => 'global',
            'site_name' => Cache::get('site_name', 'Haven Syria'),
            'contact_email' => Cache::get('contact_email', 'admin@haven.com'),
            'contact_phone' => Cache::get('contact_phone', '0994207033'),
        ];
        return response()->json(['settings' => $globalSettings], 200);
    }

    // 2. إذا كان شريك (Partner) -> يرى إعدادات مكتبه الخاص فيه فقط من الداتابيز
    if ($user->role === 'partner') {
        $office = RealEstateOffice::where('manager_id', $user->id)->first();
        if (!$office) {
            return response()->json(['message' => 'أنت لست مديراً لأي مكتب حالياً'], 404);
        }

        return response()->json([
            'type' => 'office',
            'office_settings' => [
                'name' => $office->name,
                'location' => $office->location,
                'phone' => $office->phone,
                'description' => $office->description,
                'logo_path' => $office->logo_path,
            ]
        ], 200);
    }

    return response()->json(['message' => 'غير مصرح لك بدخول الإعدادات'], 403);
}

// تحديث الإعدادات (النظام للآدمن / المكتب للشريك)
public function update(Request $request)
{
    $user = $request->user();

    // 1. تحديث إعدادات السيرفر العامة (خاص بالآدمن فقط 🔒)
    if ($user->role === 'admin') {
        $request->validate([
            'site_name' => 'required|string',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string',
        ]);

        Cache::forever('site_name', $request->site_name);
        Cache::forever('contact_email', $request->contact_email);
        Cache::forever('contact_phone', $request->contact_phone);

        return response()->json(['message' => 'تم تحديث إعدادات النظام العامة بنجاح'], 200);
    }

    // 2. تحديث إعدادات المكتب (خاص بالبارتنر فقط 🔒)
    if ($user->role === 'partner') {
        $office = RealEstateOffice::where('manager_id', $user->id)->first();
        if (!$office) {
            return response()->json(['message' => 'لا تملك مكتباً لتحديث بياناته'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string',
            'phone' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $office->update([
            'name' => $request->name,
            'location' => $request->location,
            'phone' => $request->phone,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'تم تحديث بيانات مكتبك العقاري بنجاح'], 200);
    }

    return response()->json(['message' => 'إجراء غير مسموح به'], 403);
}}
