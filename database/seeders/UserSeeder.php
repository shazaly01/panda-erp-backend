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
        // 1. إنشاء أو تحديث مستخدم Super Admin
        $superAdmin = User::updateOrCreate(
            ['username' => 'superadmin'], // مفتاح التحقق لمنع التكرار
            [
                'full_name' => 'Super Admin',
                'email' => 'superadmin@app.com',
                'password' => bcrypt('12345678'), // كلمة مرور موحدة لسهولة التطوير
                'email_verified_at' => now(),
            ]
        );
        // تعيين دور "Super Admin" الصحيح (تتجاهله الحزمة تلقائياً إن كان ممتلكاً له مسبقاً)
        $superAdmin->assignRole('Super Admin');


        // 2. إنشاء أو تحديث مستخدم Admin (مدير النظام)
        $adminUser = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'Admin User',
                'email' => 'admin@app.com',
                'password' => bcrypt('12345678'),
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole('Admin');


        // 3. إنشاء أو تحديث مستخدم Data Entry (مدخل بيانات)
        $dataEntryUser = User::updateOrCreate(
            ['username' => 'dataentry'],
            [
                'full_name' => 'Data Entry User',
                'email' => 'dataentry@app.com',
                'password' => bcrypt('12345678'),
                'email_verified_at' => now(),
            ]
        );
        $dataEntryUser->assignRole('Data Entry');


        // 4. إنشاء أو تحديث مستخدم Auditor (مراجع)
        $auditorUser = User::updateOrCreate(
            ['username' => 'auditor'],
            [
                'full_name' => 'Auditor User',
                'email' => 'auditor@app.com',
                'password' => bcrypt('12345678'),
                'email_verified_at' => now(),
            ]
        );
        $auditorUser->assignRole('Auditor');
    }
}
