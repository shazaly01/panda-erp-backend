<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected AuthService $authService;

    /**
     * حقن خدمة التوثيق داخل الباني
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * تسجيل الدخول المعتمد على رقم الهاتف كمُعرّف أساسي
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string|regex:/^[0-9]{10}$/',
            'password' => 'required|string',
        ], [
            'phone.required' => 'حقل رقم الهاتف مطلوب.',
            'phone.regex'    => 'يجب أن يتكون رقم الهاتف من 10 أرقام فقط.',
            'password.required' => 'حقل كلمة المرور مطلوب.'
        ]);

        // تنظيف ودمج مفتاح الدولة المعتمد ليتطابق مع المخزن في قاعدة البيانات
        $cleanedPhone = str_replace(' ', '', $request->phone);
        $fullPhone = config('app.country_code') . $cleanedPhone;

        // البحث عن المستخدم عبر رقم الهاتف الكامل
        $user = User::where('phone', $fullPhone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        // منع الحسابات المعلقة من الدخول لحين اعتماد المشرف
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'حسابك قيد المراجعة حالياً، يرجى تواصلك مع الإدارة لتفعيل الحساب ومنح الصلاحيات.'
            ], 403);
        }

        $user->load('roles.permissions');

        // حذف التوكنات القديمة لتجنب التراكم
        $user->tokens()->delete();

        // إنشاء توكن جديد (Sanctum)
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message'      => 'Login successful',
            'access_token' => $token,
            'user'         => new UserResource($user),
        ]);
    }

    /**
     * إرسال رمز التحقق (OTP) للمتصفح قبل التسجيل الذاتي
     */
    public function sendOtp(SendOtpRequest $request)
    {
        try {
            // جلب البيانات بعد الفحص والتحويل لضمان وجود المفتاح الدولي
            $validated = $request->validated();

            $this->authService->sendOtp(
                $validated['phone'],
                $request->input('build_mode', 'release')
            );

            return response()->json(['message' => 'تم إرسال رمز التحقق بنجاح.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * التسجيل الذاتي للمخدم بالهاتف (ينشأ الحساب بحالة pending)
     */
    public function register(RegisterRequest $request)
    {
        try {
            $user = $this->authService->registerPendingUser($request->validated());

            return response()->json([
                'message' => 'تم تسجيل حسابك بنجاح وهو بانتظار موافقة المشرف وتعيين الصلاحيات.',
                'user'    => new UserResource($user)
            ], 201);
        } catch (\Exception $e) {
            $code = $e->getCode() == 401 ? 401 : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * طلب كود استعادة كلمة المرور عبر رقم الهاتف
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            // جلب البيانات المفلترة والمعدلة من الـ Request المعماري
            $validated = $request->validated();

            $this->authService->sendResetPasswordOtp($validated['phone']);

            return response()->json([
                'message' => 'تم إرسال رمز استعادة كلمة المرور إلى رقم هاتفك بنجاح.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * تأكيد كود الاستعادة المربوط بالهاتف وتغيير كلمة المرور
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $this->authService->resetPasswordWithOtp($request->validated());

            return response()->json([
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح، يمكنك الآن تسجيل الدخول باستخدام البيانات الجديدة.'
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() == 401 ? 401 : 500;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
