<?php

namespace App\Http\Controllers\Api;
use App\Models\Property;
use App\Models\RealEstateOffice;
use App\Models\PropertyImage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{

    // عرض عقارات مكتب معين (تصفح)
    public function index($officeId)
    {
        // 1. التحقق من وجود المكتب
        $office = RealEstateOffice::findOrFail($officeId);

        // 2. جلب عقارات المكتب المقبولة والمتاحة فقط
        $properties = Property::with(['images'])
            ->where('office_id', $officeId)
            ->where('approval_status', 'accepted')
            ->where('status', 'available')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $properties->count(),
            'data' => $properties,
        ]);
    }

    // عرض تفاصيل عقار معين
    public function show($id)
    {
        // 🌟 تحسين أمني: تم إزالة شحن علاقة الـ owner كلياً لحماية خصوصية المالك ومنع الزبون من تخطي المكتب
        $property = Property::with(['office', 'images'])
            ->where('approval_status', 'accepted')
            ->findOrFail($id);

        $images = $property->images->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->image_path,
            ];    
        });
       
        // ========== معالجة الموقع (GPS) ==========
        $location = null;
        if ($property->location_gps) {
            if (str_contains($property->location_gps, ',')) {
                $coords = explode(',', $property->location_gps);
                $location = [
                    'lat' => trim($coords[0] ?? ''),
                    'lng' => trim($coords[1] ?? ''),
                    'formatted' => $property->location_gps,
                ];
            } elseif (is_string($property->location_gps)) {
                $decoded = json_decode($property->location_gps, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $location = [
                        'lat' => $decoded['lat'] ?? $decoded['latitude'] ?? '',
                        'lng' => $decoded['lng'] ?? $decoded['longitude'] ?? '',
                        'formatted' => $property->location_gps,
                    ];
                } else {
                    $location = [
                        'raw' => $property->location_gps,
                        'formatted' => $property->location_gps,
                    ];
                }
            }
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $property->id,
                'title' => $property->title,
                'description' => $property->description,
                
                // ========== الأسعار ==========
                'price_sp' => $property->price_sp,
                'price_sp_formatted' => $property->price_sp ? number_format($property->price_sp, 0, '.', ',') . ' ل.س' : null,
                'price_usd' => $property->price_usd,
                'price_usd_formatted' => $property->price_usd ? number_format($property->price_usd, 0, '.', ',') . ' $' : null,
                
                // ========== المعلومات الأساسية ==========
                'region' => $property->region,
                'property_type' => $property->property_type,
                'offer_type' => $property->offer_type, // بيع أو آجار
                'rent_period' => $property->rent_period,
                'is_furnished' => $property->is_furnished,
                
                // ========== التفاصيل ==========
                'area' => $property->area,
                'area_formatted' => number_format($property->area, 0, '.', ',') . ' م²',
                'rooms_count' => $property->rooms_count,
                'floor_number' => $property->floor_number,
                'status' => $property->status,
                
                // ========== الموقع ==========
                'address_details' => $property->address_details,
                'location_gps' => $location,
                
                // ========== الصور ==========
                'images' => $images,
                
                // ========== المكتب (للاتصال والـ WhatsApp) ==========
                'office' => $property->office ? [
                    'id' => $property->office->id,
                    'name' => $property->office->name,
                    'phone' => $property->office->phone, // 📞 الزبون بيتواصل هون بس
                    'location' => $property->office->location,
                ] : null,
                
                // ========== التواريخ ==========
                'created_at' => $property->created_at->toDateTimeString(),
                'created_human' => $property->created_at->diffForHumans(),
                'updated_at' => $property->updated_at->toDateTimeString(),
            ]
        ]);
    }

    // الفلترة والتصفح المتقدم
    public function browse(Request $request)
    {
        // ========== بناء الاستعلام الأساسي (تم الاستغناء عن شحن الـ owner لعدم الحاجة له هنا) ==========
        $query = Property::with(['office', 'images'])
            ->where('approval_status', 'accepted')
            ->where('status', 'available');

        // ========== 1. الفلترة حسب نوع العرض (بيع / آجار) 🌟 تحسين جوهري لم يكن موجوداً ==========
        if ($request->has('offer_type') && $request->offer_type) {
            $query->where('offer_type', $request->offer_type);
        }

        // ========== 2. فلترة حسب السعر بالليرة السورية ==========
        if ($request->has('min_price_sp') && $request->min_price_sp) {
            $query->where('price_sp', '>=', $request->min_price_sp);
        }
        if ($request->has('max_price_sp') && $request->max_price_sp) {
            $query->where('price_sp', '<=', $request->max_price_sp);
        }

        // ========== السعر بالدولار ==========
        if ($request->has('min_price_usd') && $request->min_price_usd) {
            $query->where('price_usd', '>=', $request->min_price_usd);
        }
        if ($request->has('max_price_usd') && $request->max_price_usd) {
            $query->where('price_usd', '<=', $request->max_price_usd);
        }

        // ========== 3. فلترة حسب المنطقة ==========
        if ($request->has('region') && $request->region) {
            $regions = explode(',', $request->region);
            $query->whereIn('region', $regions);
        }
        // ========== 4. فلترة حسب نوع العقار ==========
        if ($request->has('property_type') && $request->property_type) {
            $types = explode(',', $request->property_type);
            $query->whereIn('property_type', $types);
        }

        $filteredCount = (clone $query)->count(); 
          
        // ========== 5. التصفح (Pagination) ==========
        $perPage = $request->per_page ?? 15;
        $properties = $query->paginate($perPage);

        // ========== 6. تنسيق البيانات المرسلة للعرض القائم على الكروت (Cards) ==========
        $formattedProperties = $properties->getCollection()->map(function ($property) {
            return [
                'id' => $property->id,
                'title' => $property->title,
                'description' => substr($property->description ?? '', 0, 150) . '...',
                'price_sp' => $property->price_sp,
                'price_usd' => $property->price_usd,
                'region' => $property->region,
                'property_type' => $property->property_type,
                'offer_type' => $property->offer_type,
                'area' => $property->area,
                'area_formatted' => number_format($property->area, 0, '.', ',') . ' م²',
                'rooms_count' => $property->rooms_count,
                
                // 🌟 تحسين ناري: إرسال الصور للمطور في الـ Frontend عشان تظهر الكروت بصورها المصغرة
                'images' => $property->images->map(function($img) {
                    return ['id' => $img->id, 'url' => $img->image_path];
                }),

                'office' => $property->office ? [
                    'id' => $property->office->id,
                    'name' => $property->office->name,
                    'phone' => $property->office->phone,
                    'location' => $property->office->location,
                ] : null,
                'created_at' => $property->created_at->toDateTimeString(),
                'created_human' => $property->created_at->diffForHumans(),
            ];
        });

        // ========== 7. إحصائيات الفلترة الديناميكية ==========
        $stats = [
            'total' => Property::where('approval_status', 'accepted')->where('status', 'available')->count(),
            'filtered' => $filteredCount, 
            'price_range' => [
                'min_sp' => Property::where('approval_status', 'accepted')->where('status', 'available')->min('price_sp'),
                'max_sp' => Property::where('approval_status', 'accepted')->where('status', 'available')->max('price_sp'),
                'min_usd' => Property::where('approval_status', 'accepted')->where('status', 'available')->min('price_usd'),
                'max_usd' => Property::where('approval_status', 'accepted')->where('status', 'available')->max('price_usd'),
            ],
            'regions' => Property::where('approval_status', 'accepted')->where('status', 'available')->distinct()->pluck('region'),
            'property_types' => Property::where('approval_status', 'accepted')->where('status', 'available')->distinct()->pluck('property_type'),
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedProperties,
            'stats' => $stats,
            'meta' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
                'from' => $properties->firstItem(),
                'to' => $properties->lastItem(),
            ],
        ]);
    }
} 