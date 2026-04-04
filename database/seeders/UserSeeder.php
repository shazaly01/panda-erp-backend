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
        // 1. إنشاء مستخدم Super Admin
        $superAdmin = User::create([
            'full_name' => 'Super Admin',
            'username' => 'superadmin', // username فريد
            'email' => 'superadmin@app.com',
            'password' => bcrypt('12345678'), // كلمة مرور موحدة لسهولة التطوير
            'email_verified_at' => now(),
        ]);
        // تعيين دور "Super Admin" الصحيح
        $superAdmin->assignRole('Super Admin');


        // 2. إنشاء مستخدم Admin (مدير النظام)
        $adminUser = User::create([
            'full_name' => 'Admin User',
            'username' => 'admin', // username فريد
            'email' => 'admin@app.com',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        // تعيين دور "Admin" الصحيح
        $adminUser->assignRole('Admin');


        // 3. إنشاء مستخدم Data Entry (مدخل بيانات)
        $dataEntryUser = User::create([
            'full_name' => 'Data Entry User',
            'username' => 'dataentry', // username فريد
            'email' => 'dataentry@app.com',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        // تعيين دور "Data Entry" الصحيح
        $dataEntryUser->assignRole('Data Entry');


        // 4. إنشاء مستخدم Auditor (مراجع)
        $auditorUser = User::create([
            'full_name' => 'Auditor User',
            'username' => 'auditor', // username فريد
            'email' => 'auditor@app.com',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
        ]);
        // تعيين دور "Auditor" الصحيح
        $auditorUser->assignRole('Auditor');
    }
}
