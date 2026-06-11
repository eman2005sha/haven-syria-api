<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
   public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. التأكد أن المستخدم مسجل دخول أصلاً
        if (!$request->user()) {
            return response()->json(['message' => 'غير مصرح لك بالدخول، يرجى تسجيل الدخول أولاً'], 401);
        }

        // 2. الفحص إذا كان الـ role تبع المستخدم موجود ضمن الصلاحيات المسموح بها
        if (!in_array($request->user()->role, $roles)) {
            return response()->json(['message' => 'خطأ في الصلاحيات! لا تملك الإذن للقيام بهذا الإجراء'], 403);
        }

        return $next($request);
    }
}
