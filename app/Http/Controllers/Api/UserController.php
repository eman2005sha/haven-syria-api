<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

// إدارة حسابات الشركاء والمدراء (خاصة بالسوبر آدمن فقط) — روابطها محمية بـ role:admin
class UserController extends Controller
{
    // 1. عرض قائمة حسابات المدراء والشركاء
    public function index(Request $request)
    {
        $users = User::with('managedOffice')
            ->whereIn('role', ['admin', 'partner'])
            ->latest()
            ->get()
            ->map(fn (User $user) => $this->formatUser($user));

        return response()->json([
            'success' => true,
            'users' => $users,
        ], 200);
    }

    // 2. إنشاء حساب مدير أو شريك جديد مباشرة من قبل السوبر آدمن (بدون حاجة لتفعيل OTP)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|min:10|max:15',
            'role' => 'required|in:admin,partner',
            'password' => 'required|string|min:8',
            'office_id' => 'nullable|exists:real_estate_offices,id',
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
            'email_verified_at' => now(), // حساب منشأ يدوياً من الآدمن، مفعّل فوراً
        ]);

        if ($request->office_id) {
            \App\Models\RealEstateOffice::where('id', $request->office_id)
                ->update(['manager_id' => $user->id]);
        }

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح.',
            'user' => $this->formatUser($user->fresh('managedOffice')),
        ], 201);
    }

    // 3. تحديث حالة الحساب (تفعيل / تعطيل)
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك تعطيل حسابك الخاص'], 400);
        }

        $user->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'message' => $user->is_active ? 'تم تفعيل الحساب بنجاح' : 'تم تعطيل الحساب بنجاح',
            'user' => $this->formatUser($user->fresh('managedOffice')),
        ], 200);
    }

    // 4. حذف حساب مدير أو شريك
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الخاص'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'تم حذف الحساب بنجاح'], 200);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'office' => $user->managedOffice ? [
                'id' => $user->managedOffice->id,
                'name' => $user->managedOffice->name,
            ] : null,
        ];
    }
}
