<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    //عرض  واجهة إعدادات النظام لل الأدمن والشريك 
    public function index()
    {
        $settings = [
            'site_name' => Cache::get('site_name','  بيتك دليلك'),
            'contact_email' => Cache::get('contact_email', 'amar22@gmail.com'),
            'contact_phone' => Cache::get('contact_phone', '0994207033'),
        ];
        return response()->json([
              'settings' => $settings 
        ], 200);
    }
// تحديث إعدادا النظام متاح لل الأدمن والشريك
    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string',
            'contact_email' => 'required|email',
            'contact_phone' => 'required|string',
        ]);

        Cache::forever('site_name', $request->site_name);
        Cache::forever('contact_email', $request->contact_email);
        Cache::forever('contact_phone', $request->contact_phone);

      
        return response()->json([
            'message' => 'تم تحديث إعدادات النظام بنجاح'
        ], 200);
    }
}
