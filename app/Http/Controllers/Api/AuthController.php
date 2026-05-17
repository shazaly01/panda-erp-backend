<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        // تحميل الأدوار والصلاحيات المرتبطة بها في قاعدة البيانات فقط
        // ملاحظة للواجهة (Frontend): تجاوز الـ Super Admin يتم الآن بمعرفة الواجهة عبر اسم الدور
        // والباك إند محمي مركزياً عبر Gate::before
        $user->load('roles.permissions');

        // حذف التوكنات القديمة لتجنب التراكم
        $user->tokens()->delete();

        // ننشئ توكن جديد
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        // حذف التوكن الحالي فقط
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
