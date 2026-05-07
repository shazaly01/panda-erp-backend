<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfflineEmployeesBatch4Seeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['ArabicName' => 'نادمين يوحنا', 'EmployeeName' => 'Nadeem yuhanna', 'EmpCode' => '20101210250', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ادم ابراهيم ابكر', 'EmployeeName' => 'Adam Ibrahim Abaker', 'EmpCode' => '20101210251', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'موفق عركي مختار ادم', 'EmployeeName' => 'Muffag Araki Mukhtar', 'EmpCode' => '20101210252', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'محمد الطيب عبدالرحمن', 'EmployeeName' => 'Mohamed El Teib A.Ra', 'EmpCode' => '20101210253', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'محمد احمد عيسي', 'EmployeeName' => 'Mohamed Ahmed Eisa', 'EmpCode' => '20101210254', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'وير ويويل جاركوس', 'EmployeeName' => 'Were Wewal Garques', 'EmpCode' => '20101210255', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عبدالجبار فضل السيد فضل المولي جادين', 'EmployeeName' => 'Abd El Gabar Fadl Elseed Fadl Elmoula Gadeen', 'EmpCode' => '20101210256', 'HireDate' => '2024-09-20', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'محمد كمال خليل', 'EmployeeName' => 'Mohamed Kamal Khaleel', 'EmpCode' => '20101210257', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'بدرالدين بلوله محمد الحسن', 'EmployeeName' => 'Babr Eldeen Balula Mohamed', 'EmpCode' => '20101210258', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'احمد كمال الدين خليل شوربجي', 'EmployeeName' => 'Ahmed Kamalaldeen Khalil Shorbagi', 'EmpCode' => '20101210259', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ياسر محمد عبدالله محمد', 'EmployeeName' => 'Yasirr Mohamed Abdalla', 'EmpCode' => '20101210260', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'البراء صابر عبدالله سعد', 'EmployeeName' => 'El Barra Sabirr Abdalla', 'EmpCode' => '20101210261', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مهند الطيب عبدالله', 'EmployeeName' => 'Muhannad El Teib Abdalla', 'EmpCode' => '20101210262', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'احمد عبدالهادي ابراهيم عبدالقادر', 'EmployeeName' => 'Ahmed AbdalHadi Ibrahim Abdalgadir', 'EmpCode' => '20101210263', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'امير يعقوب عبدالرحمن', 'EmployeeName' => 'Amir Yagoub Abdel Rahman ', 'EmpCode' => '20101210264', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'المقداد عبدالله عثمان احمد', 'EmployeeName' => 'Almgdad Abdalla Osman Ahmed', 'EmpCode' => '20101210265', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'خالد الطاهر محمد عثمان', 'EmployeeName' => 'Khaled Altaher Mohamed Osman', 'EmpCode' => '20101210266', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'احمد محمد علي', 'EmployeeName' => 'Ahmed Mohamed Ali', 'EmpCode' => '20101210267', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عرفات يوسف ادم عثمان', 'EmployeeName' => 'Arafat Yusuf Adam', 'EmpCode' => '20101210268', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'طارق جار النبي حسن', 'EmployeeName' => 'Tarig Gar El Nabie Hassan', 'EmpCode' => '20101210269', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'علي حسن الزاكي', 'EmployeeName' => 'Ali Hassan El Zakey', 'EmpCode' => '20101210270', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'علي عمر علي', 'EmployeeName' => 'Ali Omer Ali', 'EmpCode' => '20101210271', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'وردي جارالنبي حسن عبدالله', 'EmployeeName' => 'Wardi Gar El Nabie Hassan', 'EmpCode' => '20101210272', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عيسي عمر هارون اسحق', 'EmployeeName' => 'Eisa Omer Haroon', 'EmpCode' => '20101210273', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'برعي جار النبي حسن', 'EmployeeName' => 'Burae Gar El Nabie Hassan', 'EmpCode' => '20101210274', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عثمان ادم', 'EmployeeName' => 'Osman Adam', 'EmpCode' => '20101210275', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عبدالرحمن ابراهيم موسي عبدالتام', 'EmployeeName' => 'Abdelrhaman Ibrahim musa', 'EmpCode' => '20101210276', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مبارك باشري الطاهر باشري', 'EmployeeName' => 'Mubarak Basharee Altahir Basharee', 'EmpCode' => '20101210277', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'يزيد الحاج احمد', 'EmployeeName' => 'Yazeed El Haj Ahmed', 'EmpCode' => '20101210278', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'يوسف الفكي الرشيد', 'EmployeeName' => 'Yousif El Faki Erasheed', 'EmpCode' => '20101210279', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
        ];

        DB::transaction(function () use ($employees) {
            $startingNumber = 153;
            $now = Carbon::now();

            foreach ($employees as $emp) {
                $employeeNumber = 'EMP-' . str_pad((string)$startingNumber, 5, '0', STR_PAD_LEFT);

                DB::table('employees')->insert([
                    'full_name' => $emp['ArabicName'],
                    'full_english_name' => $emp['EmployeeName'],
                    'barcode' => $emp['EmpCode'],
                    'join_date' => Carbon::parse($emp['HireDate'])->format('Y-m-d'),
                    'status' => $emp['Status'],
                    'employment_type' => 'full_time',
                    'department_id' => 1,
                    'position_id' => 1,
                    'user_id' => null,
                    'employee_number' => $employeeNumber,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $startingNumber++;
            }
        });
    }
}
