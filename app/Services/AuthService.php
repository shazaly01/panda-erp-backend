<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * إرسال رمز التحقق OTP عبر بوابة BrqSMS وحفظه في الـ Cache للتسجيل الذاتي
     *
     * @param string $phone
     * @param string $buildMode
     * @return bool
     * @throws Exception
     */
    public function sendOtp(string $phone, string $buildMode = 'release'): bool
    {
        $code = (string) random_int(100000, 999999);

        $apiToken = config('services.brqsms.api_token');
        $senderId = config('services.brqsms.sender_id');

        if (!$apiToken || !$senderId) {
            Log::critical('BrqSMS Service is not configured. Check .env file for BRQSMS_API_TOKEN and BRQSMS_SENDER_ID.');
            throw new Exception('خدمة الرسائل غير مهيأة بشكل صحيح على الخادم.');
        }

        $signatureKey = ($buildMode === 'debug')
            ? 'services.flutter.app_signature_debug'
            : 'services.flutter.app_signature_release';

        $appSignature = config($signatureKey);

        $messageBody = "رمز التحقق الخاص بك هو: " . $code;
        if ($appSignature) {
            $messageBody .= "\n\n" . $appSignature;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post('https://dash.brqsms.com/api/v3/sms/send', [
            'recipient' => $phone,
            'sender_id' => $senderId,
            'type'      => 'plain',
            'message'   => $messageBody,
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            Log::error('BrqSMS Sending Failed:', [
                'phone'           => $phone,
                'response_status' => $response->status(),
                'response_body'   => $response->json()
            ]);
            throw new Exception($response->json('message') ?? 'فشل إرسال رمز التحقق من مزود الخدمة.');
        }

        // تخزين الكود في الكاش لمدة 5 دقائق بربطه برقم الهاتف
        Cache::put('verification_code_' . $phone, $code, now()->addMinutes(5));

        return true;
    }

    /**
     * معالجة عملية التسجيل الذاتي والتحقق من الـ OTP وإنشاء الحساب بحالة معلقة
     *
     * @param array $data
     * @return User
     * @throws Exception
     */
    public function registerPendingUser(array $data): User
    {
        $cacheKey = 'verification_code_' . $data['phone'];
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $data['code']) {
            throw new Exception('رمز التحقق غير صالح أو انتهت صلاحيته.', 401);
        }

        // مسح الرمز من الكاش بعد التحقق الناجح لمنع إعادة الاستخدام
        Cache::forget($cacheKey);

        DB::beginTransaction();
        try {
            $user = User::create([
                'full_name'         => $data['full_name'],
                'username'          => $data['username'] ?? null, // أصبح اختيارياً بالكامل مع الهاتف
                'phone'             => $data['phone'],
                'password'          => Hash::make($data['password']),
                'status'            => 'pending', // الحساب معلق دائماً بانتظار المشرف
                'phone_verified_at' => now(),     // تم التحقق من الهاتف بنجاح
            ]);

            DB::commit();
            return $user;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('User Registration Service Error: ' . $e->getMessage());
            throw new Exception('حدث خطأ داخلي أثناء إنشاء الحساب.');
        }
    }

    /**
     * إرسال رمز استعادة كلمة المرور عبر الهاتف باستخدام رقم الهاتف مباشرة
     *
     * @param string $phone
     * @return bool
     * @throws Exception
     */
    public function sendResetPasswordOtp(string $phone): bool
    {
        // البحث عن المستخدم مباشرة عبر رقم هاتفه
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            throw new Exception('رقم الهاتف المدخل غير مسجل مسبقاً في النظام.', 422);
        }

        $code = (string) random_int(100000, 999999);

        $apiToken = config('services.brqsms.api_token');
        $senderId = config('services.brqsms.sender_id');

        if (!$apiToken || !$senderId) {
            Log::critical('BrqSMS Service is not configured for Password Reset.');
            throw new Exception('خدمة الرسائل غير مهيأة بشكل صحيح على الخادم.');
        }

        $messageBody = "رمز إعادة تعيين كلمة المرور الخاص بك لنظام ERP هو: " . $code;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post('https://dash.brqsms.com/api/v3/sms/send', [
            'recipient' => $phone,
            'sender_id' => $senderId,
            'type'      => 'plain',
            'message'   => $messageBody,
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            Log::error('BrqSMS Reset Password Sending Failed:', [
                'phone' => $phone
            ]);
            throw new Exception('فشل إرسال رمز استعادة كلمة المرور من مزود الخدمة.');
        }

        // تخزين كود الاستعادة في الكاش بربطه برقم الهاتف مباشرة لمدة 5 دقائق
        Cache::put('password_reset_code_' . $phone, $code, now()->addMinutes(5));

        return true;
    }

    /**
     * التحقق من كود الاستعادة المرتبط بالهاتف وتحديث كلمة المرور الجديدة
     *
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function resetPasswordWithOtp(array $data): bool
    {
        // جلب كود الاستعادة المربوط بالهاتف
        $cacheKey = 'password_reset_code_' . $data['phone'];
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $data['code']) {
            throw new Exception('رمز التحقق غير صالح أو انتهت صلاحيته.', 401);
        }

        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            throw new Exception('المستخدم المرتبط برقم الهاتف هذا غير موجود بالنظام.', 442);
        }

        DB::beginTransaction();
        try {
            // تحديث كلمة المرور الجديدة مشفرة
            $user->update([
                'password' => Hash::make($data['password']),
            ]);

            // حماية إضافية: حذف كافة توكنات الجلسات المتصلة بالهاتف لإجبار المستخدم على الدخول بالبينات الجديدة
            $user->tokens()->delete();

            // إزالة الرمز من الكاش بعد نجاح التغيير
            Cache::forget($cacheKey);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Password Reset Execution Error: ' . $e->getMessage());
            throw new Exception('حدث خطأ داخلي أثناء إعادة تعيين كلمة المرور الجديدة.');
        }
    }
}
