<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfflineEmployeesBatch3Seeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['ArabicName' => 'محمد يوسف ادم عثمان', 'EmployeeName' => 'Mohamed Yousif  Adam Osman', 'EmpCode' => '20101210200', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ادم صالح ادم اسحق', 'EmployeeName' => 'Adam Saleh Adam Eshag', 'EmpCode' => '20101210201', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'بابكر يحي اسماعيل', 'EmployeeName' => 'Babiker Yahya Ismeil', 'EmpCode' => '20101210202', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'علي عمر علي', 'EmployeeName' => 'Ali Omer Ali', 'EmpCode' => '20101210203', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'علي مطر ابوصلعه', 'EmployeeName' => 'Ali Mattar AbSalaa', 'EmpCode' => '20101210204', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'محمد حسن حاج الزاكي يوسف', 'EmployeeName' => 'Mohamed Hassan ElZakey', 'EmpCode' => '20101210205', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'امجد علي حسن', 'EmployeeName' => 'Amjad Ali Hassan', 'EmpCode' => '20101210206', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'داوؤد اكير اكول', 'EmployeeName' => 'Dawood Arkij Ackol', 'EmpCode' => '20101210207', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ارميا علي مطر', 'EmployeeName' => 'Armiaa Ali Mattar', 'EmpCode' => '20101210208', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'استيفن ديفت كنجوك', 'EmployeeName' => 'Stevan David Kngock', 'EmpCode' => '20101210209', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مين فجري ماشيك', 'EmployeeName' => 'Main Furraij Masheek', 'EmpCode' => '20101210210', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'سبت وليم', 'EmployeeName' => 'Sabit Wiiliams', 'EmpCode' => '20101210211', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ناصر ادم علي عبدالله', 'EmployeeName' => 'Nasir Adam Ali', 'EmpCode' => '20101210212', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'موسي ادم علي', 'EmployeeName' => 'Musa Adam Ali', 'EmpCode' => '20101210213', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'دكتور ويويل جيمس', 'EmployeeName' => 'Doctor Wewal Garquess', 'EmpCode' => '20101210214', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عبدالسلام جار النبي حسن عبدالله', 'EmployeeName' => 'A.Salam Gar El Nabey', 'EmpCode' => '20101210215', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'احمد عبدالمطلب عثمان الطاهر', 'EmployeeName' => 'Ahmed Abdel Muttalab Osman Altahir', 'EmpCode' => '20101210216', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'عمر محمد احمد', 'EmployeeName' => 'Omer Mohamed Ahmed', 'EmpCode' => '20101210217', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'جمعه بيتر علي', 'EmployeeName' => 'Jumaa Peter Ali', 'EmpCode' => '20101210218', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'جون داوود باك', 'EmployeeName' => 'John Daewoo Back', 'EmpCode' => '20101210219', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'احمد علي صابر', 'EmployeeName' => 'Ahmed Ali Sabirr', 'EmpCode' => '20101210220', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'علاء الدين بشري عبدالله ادريس', 'EmployeeName' => 'Alaa Eldeen Bushra', 'EmpCode' => '20101210221', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'حسن جار النبي حسن عبدالله', 'EmployeeName' => 'Hassan Gar El Nabey', 'EmpCode' => '20101210222', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'كريس سبت ', 'EmployeeName' => 'Chris Sabit Ommom', 'EmpCode' => '20101210223', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ايمن عادل حموده محمد', 'EmployeeName' => 'Ayman Adil Hamouda', 'EmpCode' => '20101210224', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'نيوت ديفيد كنجوك', 'EmployeeName' => 'Neyut David Kngock ', 'EmpCode' => '20101210225', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'يعقوب عبدالرحمن مطر ابوصلع', 'EmployeeName' => 'Yagoub Abdel Rahman Matr Aboslaa', 'EmpCode' => '20101210226', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'سيف عوض', 'EmployeeName' => 'Saif Awad', 'EmpCode' => '20101210227', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'العبادى نصرالدين النور ابراهيم', 'EmployeeName' => 'AlAbadi Nasraldin Alnour Ibrahim', 'EmpCode' => '20101210228', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ريكي علي مطر', 'EmployeeName' => 'Reeki Ali Mattar', 'EmpCode' => '20101210229', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'سيف الدوله الشيخ احمد المصطفي', 'EmployeeName' => 'Saif Eldoula Elsheikh', 'EmpCode' => '20101210230', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'بحر محمد ادم موسي', 'EmployeeName' => 'Bahar Mohamed Adam', 'EmpCode' => '20101210231', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'بابكر ادم علي', 'EmployeeName' => 'Babiker Adam Ali', 'EmpCode' => '20101210232', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مبارك يوسف ادم', 'EmployeeName' => 'Mubarak Yousif Adam', 'EmpCode' => '20101210233', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'خليل عبدالله', 'EmployeeName' => 'Khaleel Abdalla', 'EmpCode' => '20101210234', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ابرم دومنيك', 'EmployeeName' => 'Abram Dominic', 'EmpCode' => '20101210235', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'اقوير قرند', 'EmployeeName' => 'Aguair Garand ', 'EmpCode' => '20101210236', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ياسر احمد ابراهيم', 'EmployeeName' => 'Yasirr Ahmed Ibrahim', 'EmpCode' => '20101210237', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'هجو الماحي حسين', 'EmployeeName' => 'Haju Elmahi Hussein', 'EmpCode' => '20101210238', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'يحي عبدالله محمد عبدالله', 'EmployeeName' => 'Yahya Abdalla Mohamed', 'EmpCode' => '20101210239', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'ايمن نزار محمد', 'EmployeeName' => 'Ayman Nezar Mohamed', 'EmpCode' => '20101210240', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'شاذلي احمد محمد علي', 'EmployeeName' => 'shazaly Ahmed Mohamed Ali', 'EmpCode' => '20101210241', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'محمد خالد قرشي محمد زين', 'EmployeeName' => 'Mohamed Khalid Gurashi Mohamed Zain', 'EmpCode' => '20101210242', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مبارك اسامة مبارك عثمان', 'EmployeeName' => 'Mubarak Osama Mubarak Osman', 'EmpCode' => '20101210243', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'احمد عبدالله احمد وادي', 'EmployeeName' => 'Ahmed AbdAlla Ahmed', 'EmpCode' => '20101210244', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مصطفي علي الطيب', 'EmployeeName' => 'Mustafa Ali El Teib', 'EmpCode' => '20101210245', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'الطاهر عمر هارون عيسي', 'EmployeeName' => 'El Tahirr Omer', 'EmpCode' => '20101210246', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'حاتم علي حسن جاد السيد', 'EmployeeName' => 'Hatim Ali Hassan Gadelseed', 'EmpCode' => '20101210247', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'مجاهد يوسف ادم', 'EmployeeName' => 'Mujahid Yusuf Adam', 'EmpCode' => '20101210248', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
            ['ArabicName' => 'محمد احمد صالح', 'EmployeeName' => 'Mohamed Ahmed Salih', 'EmpCode' => '20101210249', 'HireDate' => '2023-06-14', 'Status' => 'temporary_transfer'],
        ];

        DB::transaction(function () use ($employees) {
            $startingNumber = 103;
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
