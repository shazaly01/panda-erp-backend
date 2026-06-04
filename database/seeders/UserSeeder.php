<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // جلب مفتاح الدولة المعتمد في الإعدادات لتوحيد هيكلية البيانات في قاعدة البيانات
        $countryCode = config('app.country_code', '+218');

        // 1. إنشاء أو تحديث مستخدم Super Admin
        $superAdmin = User::updateOrCreate(
            ['username' => 'superadmin'], // الفحص باسم المستخدم لأن السجلات القديمة موجودة به فعلياً
            [
                'full_name'         => 'Super Admin',
                'phone'             => $countryCode . '0500000001', // دمج المفتاح ليتطابق مع فحص الـ AuthController
                'email'             => 'superadmin@app.com',
                'password'          => '12345678',   // يتم تشفيره تلقائياً عبر الكاست في الموديل
                'status'            => 'active',     // تفعيل الحساب فوراً
                'phone_verified_at' => now(),        // توثيق رقم الهاتف
            ]
        );
        $superAdmin->assignRole('Super Admin');

        // 2. إنشاء أو تحديث مستخدم Admin (مدير النظام)
        $adminUser = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'full_name'         => 'Admin User',
                'phone'             => $countryCode . '0500000002',
                'email'             => 'admin@app.com',
                'password'          => '12345678',
                'status'            => 'active',
                'phone_verified_at' => now(),
            ]
        );
        $adminUser->assignRole('Admin');

        // 3. إنشاء أو تحديث مستخدم Data Entry (مدخل بيانات)
        $dataEntryUser = User::updateOrCreate(
            ['username' => 'dataentry'],
            [
                'full_name'         => 'Data Entry User',
                'phone'             => $countryCode . '0500000003',
                'email'             => 'dataentry@app.com',
                'password'          => '12345678',
                'status'            => 'active',
                'phone_verified_at' => now(),
            ]
        );
        $dataEntryUser->assignRole('Data Entry');

        // 4. إنشاء أو تحديث مستخدم Auditor (مراجع)
        $auditorUser = User::updateOrCreate(
            ['username' => 'auditor'],
            [
                'full_name'         => 'Auditor User',
                'phone'             => $countryCode . '0500000004',
                'email'             => 'auditor@app.com',
                'password'          => '12345678',
                'status'            => 'active',
                'phone_verified_at' => now(),
            ]
        );
        $auditorUser->assignRole('Auditor');
    }
}
