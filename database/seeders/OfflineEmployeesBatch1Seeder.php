<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfflineEmployeesBatch1Seeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['ArabicName' => 'Almanaea Employees', 'EmployeeName' => 'Almanaea Employees', 'EmpCode' => '20101210100', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد احمد عبدالقيوم الطاهر باشري', 'EmployeeName' => 'Mohammed  Ahamed AbdLgayom Altahir', 'EmpCode' => '20101210101', 'HireDate' => '2010-06-01'],
            ['ArabicName' => 'الماحي الحسين الماحي', 'EmployeeName' => 'Almahi Al Hassien Almahi', 'EmpCode' => '20101210102', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'حسام الدين محمد توم', 'EmployeeName' => 'Hossam Eldeen Mohamed Toum', 'EmpCode' => '20101210103', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ابوبكر احمد حمد سليمان', 'EmployeeName' => 'Abubaker Ahmed Hamad Suliman', 'EmpCode' => '20101210104', 'HireDate' => '2010-05-01'],
            ['ArabicName' => 'ربيع الطيب عبدالرحمن محمد زين', 'EmployeeName' => 'Rabie Altayeb Abdelrahman Mohamed Zain', 'EmpCode' => '20101210105', 'HireDate' => '2022-09-01'],
            ['ArabicName' => 'عثمان صلاح عثمان بابكر', 'EmployeeName' => 'Osman Salah Osman Babiker', 'EmpCode' => '20101210106', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'صدام ذوالنون التجاني احمد', 'EmployeeName' => 'Sadam Zonoon Altigani Ahmed', 'EmpCode' => '20101210107', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عادل بشري عبدالله ادريس', 'EmployeeName' => 'Adil Bushra Abdalla Edris', 'EmpCode' => '20101210108', 'HireDate' => '2010-06-01'],
            ['ArabicName' => 'محمد الطاهر الطيب عبدالله', 'EmployeeName' => 'Mohamed El Tahir Eltaybe', 'EmpCode' => '20101210109', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مجتبي عبدالله محمود عبدالرحمن', 'EmployeeName' => 'Mojtaba Abdalla Mahmoud', 'EmpCode' => '20101210110', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'احمد علي حسن محمد', 'EmployeeName' => 'Ahmed Ali Hassan Mohamed', 'EmpCode' => '20101210111', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'علم الدين عبدالماجد النزير', 'EmployeeName' => 'Alam Eldeen Abd Elmaged El Nzeer Ahmed ', 'EmpCode' => '20101210112', 'HireDate' => '2010-05-01'],
            ['ArabicName' => 'نزار محمد مختار ادم', 'EmployeeName' => 'Nazar Mohamed Mukhtar Adam ', 'EmpCode' => '20101210113', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'اسحق ابراهيم محمد', 'EmployeeName' => 'Ishag Ibrahim Mohamed ', 'EmpCode' => '20101210114', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عبدالله الفاضل محمد عبدالله', 'EmployeeName' => 'Abd Alla Elfadil Mohamed Abd Alla', 'EmpCode' => '20101210115', 'HireDate' => '2010-06-01'],
            ['ArabicName' => 'مجاهد خلف الله عثمان الحاج', 'EmployeeName' => 'Mogahid Khlafalla Osman ', 'EmpCode' => '20101210116', 'HireDate' => '2010-05-01'],
            ['ArabicName' => 'يوسف ابراهيم بركات ابراهيم', 'EmployeeName' => 'Yousif Ibrahim Brakat Ibrahim', 'EmpCode' => '20101210117', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'هشام بابكر محمد طاهر الكمالي', 'EmployeeName' => 'Husham Babikir Mohammed Tahir Alkamaly', 'EmpCode' => '20101210118', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد علي محمد جاد السيد', 'EmployeeName' => 'Mohmmed Ali Mohammed Gadelseed', 'EmpCode' => '20101210119', 'HireDate' => '2010-05-01'],
            ['ArabicName' => 'اشرف محمد ساتي حامد', 'EmployeeName' => 'Ashraf Mohamed Satti Hamed', 'EmpCode' => '20101210120', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'علي الرضي محمد سليمان', 'EmployeeName' => 'Ali Elrady Mohamed ', 'EmpCode' => '20101210121', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يوسف الحسن ادريس خليفه', 'EmployeeName' => 'Yousif Elhassan Idris', 'EmpCode' => '20101210122', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مخلص ابراهيم حسن محمد', 'EmployeeName' => 'Mukhlis Ibrahim Hassan Mohamed', 'EmpCode' => '20101210123', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مصباح مزمل', 'EmployeeName' => 'Misbah Mozzamel', 'EmpCode' => '20101210124', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يزيد صلاح فتاحة دراج', 'EmployeeName' => 'Yazeed Salah Fataha', 'EmpCode' => '20101210125', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'زكي عثمان محمد علي', 'EmployeeName' => 'Zaki Osman Mohamed Ali', 'EmpCode' => '20101210126', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'وصال سليمان عمر علي', 'EmployeeName' => 'Wisal Suleiman Omer Ali', 'EmpCode' => '20101210127', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ابكر عبدالرحمن ابكر ادم', 'EmployeeName' => 'Abakar Abdel Rahman Abakar Adam', 'EmpCode' => '20101210128', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد محجوب عبدالله', 'EmployeeName' => 'Mohamed Mahagub Abdalla', 'EmpCode' => '20101210129', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'رويدا عبدالعظيم محجوب', 'EmployeeName' => 'Rowida Abdel Azim Mahjob', 'EmpCode' => '20101210130', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يسري صلاح الدين محمد محمد', 'EmployeeName' => 'Yousri SalahAldeen Mohammed Mohammed ', 'EmpCode' => '20101210131', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد حسين عرفات يوسف', 'EmployeeName' => 'Mohamed Hussein Arafat', 'EmpCode' => '20101210132', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ناصر حامد خمجان احمد', 'EmployeeName' => 'Nasir Hamed Khamgan Ahmed', 'EmpCode' => '20101210133', 'HireDate' => '2010-05-15'],
            ['ArabicName' => 'حمد احمد كمبال حمد', 'EmployeeName' => 'Hamed Ahmed Kambal Hamed', 'EmpCode' => '20101210134', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'اسامه صلاح الدين حسن عامر', 'EmployeeName' => 'Osama Salah Eldin Hassan Amer', 'EmpCode' => '20101210135', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مختار محمد احمد', 'EmployeeName' => 'Mokhtar Mohamed Ahmed', 'EmpCode' => '20101210136', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يحي حماد شاويش محمد', 'EmployeeName' => 'Yahia Hammad Shawish Mohamed', 'EmpCode' => '20101210137', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'الزين حسين احمد ادم', 'EmployeeName' => 'El Zain Hussein Ahmed Adam', 'EmpCode' => '20101210138', 'HireDate' => '2010-06-01'],
            ['ArabicName' => 'لمياء الريح عوض الهادي', 'EmployeeName' => 'Lamia Elraih Awad ', 'EmpCode' => '20101210139', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يوسف الطاهر محمد علي احمد', 'EmployeeName' => 'Yousif Eltahir Mohammed Ali Ahmed', 'EmpCode' => '20101210140', 'HireDate' => '2010-06-01'],
            ['ArabicName' => 'عوض الفكي الرشيد دراق', 'EmployeeName' => 'Awad El Faki Erasheed', 'EmpCode' => '20101210141', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ابراهيم ادم موسي محمد', 'EmployeeName' => 'Ibrahim Adam Musa Mohamed', 'EmpCode' => '20101210142', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'صالح محمد اسحق زكريا', 'EmployeeName' => 'Salih Mohamed Ishak ', 'EmpCode' => '20101210143', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'جار النبي حسن عبدالله ابراهيم ', 'EmployeeName' => 'Gar Elnabi Hassan Abd Alla Ibrahim', 'EmpCode' => '20101210144', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'الطيب يوسف محمد الطيب', 'EmployeeName' => 'Eltayeb Yousif Mohamed ', 'EmpCode' => '20101210145', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'خالد الطيب ادم علي', 'EmployeeName' => 'Khlid Eltayeb Adam Ali', 'EmpCode' => '20101210146', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'راشد علي مطر ابوصلعه', 'EmployeeName' => 'Rashid Ali Mattar', 'EmpCode' => '20101210147', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'اسحق الشيخ ماريق خبشا', 'EmployeeName' => 'Ishag Elshakh Marag', 'EmpCode' => '20101210148', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد الفاتح خلف الله احمد الامين ', 'EmployeeName' => 'Mohamed Elfatih Khalafalla Ahmed Elameen', 'EmpCode' => '20101210149', 'HireDate' => '2023-06-14'],
        ];

        DB::transaction(function () use ($employees) {
            $startingNumber = 3;
            $now = Carbon::now();

            foreach ($employees as $emp) {
                $employeeNumber = 'EMP-' . str_pad((string)$startingNumber, 5, '0', STR_PAD_LEFT);

                DB::table('employees')->insert([
                    'full_name' => $emp['ArabicName'],
                    'full_english_name' => $emp['EmployeeName'],
                    'barcode' => $emp['EmpCode'],
                    'join_date' => Carbon::parse($emp['HireDate'])->format('Y-m-d'),
                    'status' => 'in_service',
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
