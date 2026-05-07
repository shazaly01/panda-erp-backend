<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfflineEmployeesBatch2Seeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['ArabicName' => 'ميسره عبدالله محمد ابراهيم', 'EmployeeName' => 'Maysara Abd Alla Mohamed', 'EmpCode' => '20101210150', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ريهام محمد الحسن عوض الهادي', 'EmployeeName' => 'Riham Mohamed El Hassan Awad', 'EmpCode' => '20101210151', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عصام عبدالوهاب مختار محمد سعيد', 'EmployeeName' => 'Isaam Abdel Wahab', 'EmpCode' => '20101210152', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'حسن يوسف محمد صلاح الدين', 'EmployeeName' => 'Hassan Yousif Mohamed', 'EmpCode' => '20101210153', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عبدالحميد السر كمال الدين احمد', 'EmployeeName' => 'Abdal Hameed Elssir Kamal Eldeen Ahmed', 'EmpCode' => '20101210154', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ايمان محمد عمر الفكي', 'EmployeeName' => 'Eman Mohamed Omer', 'EmpCode' => '20101210155', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ادم عبدالرحمن ابكر شريف', 'EmployeeName' => 'Adam Abd Alrahman Abakar Shreef', 'EmpCode' => '20101210156', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يسرا محمد علي محمد احمد', 'EmployeeName' => 'Yousra Mohamed Ali', 'EmpCode' => '20101210157', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عبدالرحمن سر الختم نصر', 'EmployeeName' => 'Abdel Rahman Sir Elkhatim Nasir', 'EmpCode' => '20101210158', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ابراهيم محمد احمد محمد صالح', 'EmployeeName' => 'Ibrahim Mohamed Ahmed Mohamed Salih', 'EmpCode' => '20101210159', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ميرام انور ميرغني بابكر', 'EmployeeName' => 'Miram Anwar Merghani', 'EmpCode' => '20101210160', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد حسين احمد ادم', 'EmployeeName' => 'Mohamed Hussin Ahmed Adam', 'EmpCode' => '20101210161', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد حسن محمد مصطفي احمد', 'EmployeeName' => 'Mohamed Hassan Mohamed Mustafa', 'EmpCode' => '20101210162', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'الرشيد علي مطر', 'EmployeeName' => 'Elrasheed Ali Mattar', 'EmpCode' => '20101210163', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عبدالسلام نصر جبريل محمد', 'EmployeeName' => 'Abdelsalam Nasir Gibreel Mohamed', 'EmpCode' => '20101210164', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'احمد عبدالله احمد محمد', 'EmployeeName' => 'Ahmed AbdAlla Ahmed Mohamed', 'EmpCode' => '20101210165', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'جونسون فليب بولس ابونور', 'EmployeeName' => 'Johnson Philip Bolis', 'EmpCode' => '20101210166', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مجاهد احمد سليمان', 'EmployeeName' => 'Mogahed Ahmed Suliman', 'EmpCode' => '20101210167', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد عبدالله محمود عبدالرحمن', 'EmployeeName' => 'Mohamed AbdAlla Mahmoud', 'EmpCode' => '20101210168', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد سليمان ابراهيم سليمان', 'EmployeeName' => 'Mohamed Suliman Ibrahim', 'EmpCode' => '20101210169', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'وائل محمد شيخ الدين نمر', 'EmployeeName' => 'Waeil Mohamed Sheikheddin Nimir', 'EmpCode' => '20101210170', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'موده عثمان ادم محمد', 'EmployeeName' => 'Mawada Osman Adam Mohamed', 'EmpCode' => '20101210171', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مهيد محمد توم محمد عبدالقادر', 'EmployeeName' => 'Moheid Mohamed Toam', 'EmpCode' => '20101210172', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'فضل الله ادم مختار', 'EmployeeName' => 'Fadl Alla Adam Mukhtar', 'EmpCode' => '20101210173', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'امير بلال مرحوم جماع', 'EmployeeName' => 'Amir Bilal Marhoom', 'EmpCode' => '20101210174', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'سلمي الحاج سعيد احمد حسن', 'EmployeeName' => 'Salama Alhaj Seed Ahmed', 'EmpCode' => '20101210175', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مصطفي صالح الطاهر', 'EmployeeName' => 'Mustafa Salih Eltahir', 'EmpCode' => '20101210176', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'فائز يوسف ابراهيم بركات', 'EmployeeName' => 'Faiz Yousif Ibrahim', 'EmpCode' => '20101210177', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'مبارك ابانقش اباقرو اباقي', 'EmployeeName' => 'Mubark Abanagash', 'EmpCode' => '20101210178', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'فؤاد ذاكر', 'EmployeeName' => 'Foad Zakir', 'EmpCode' => '20101210179', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'صابر جمال', 'EmployeeName' => 'Sabir Gamal', 'EmpCode' => '20101210180', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'جون موسي اوطو', 'EmployeeName' => 'John Moses Otto Adong', 'EmpCode' => '20101210181', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'دينق شمير موين', 'EmployeeName' => 'Denk Chimir Meuon', 'EmpCode' => '20101210182', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عبدالقادر يحي حماد شاويش', 'EmployeeName' => 'Abdelgadir Yahia Hammad Shawish', 'EmpCode' => '20101210183', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'احمد سنوسي عاطف سنوسي عباس', 'EmployeeName' => 'Ahmed Sanosi Aatif Sanosi Abbas', 'EmpCode' => '20101210184', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد خميس عجلان جلاد', 'EmployeeName' => 'Mohamed Khamiss Aglaan Galad', 'EmpCode' => '20101210185', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'علي ادم علي عبدالله ', 'EmployeeName' => 'Ali Adam Ali', 'EmpCode' => '20101210186', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ام بله طاحونة ', 'EmployeeName' => 'Um Balla Tahoona', 'EmpCode' => '20101210187', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'عباس الشيخ ماريق', 'EmployeeName' => 'Abbass El Sheikh Mareg', 'EmpCode' => '20101210188', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'فخري الدين صلاح خميس بركه', 'EmployeeName' => 'Fakhradeen Salah Kha', 'EmpCode' => '20101210189', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد بابكر عبدالمولي محمد ', 'EmployeeName' => 'Mohamed Babiker Abdelmoula Mohamed', 'EmpCode' => '20101210190', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'حسن حسين محمد', 'EmployeeName' => 'Hassan Hussein Mohamed', 'EmpCode' => '20101210191', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'يوسف محمود محمد بابكر', 'EmployeeName' => 'Yousif Mahmoud Mohamed Bbiker', 'EmpCode' => '20101210192', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'اجبر الخير عيسي', 'EmployeeName' => 'Ajbar El Kair Eisa', 'EmpCode' => '20101210193', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'محمد عمر علي احمد', 'EmployeeName' => 'Mohamed Omer Ali Ahmed', 'EmpCode' => '20101210194', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'ابوذر بابو بشير بابو', 'EmployeeName' => 'Abuzarr Babou Basheer', 'EmpCode' => '20101210195', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'هيثم عبدالله', 'EmployeeName' => 'Haytham Abdalla', 'EmpCode' => '20101210196', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'امين حسين امين', 'EmployeeName' => 'Ameen Hussein Ameen', 'EmpCode' => '20101210197', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'الزين احمد محمد الزين', 'EmployeeName' => 'AlZain Ahmed Mohamed AlZain ', 'EmpCode' => '20101210198', 'HireDate' => '2023-06-14'],
            ['ArabicName' => 'الطيب احمد عبدالوهاب', 'EmployeeName' => 'El Teib Ahmed A.Wah', 'EmpCode' => '20101210199', 'HireDate' => '2023-06-14'],
        ];

        DB::transaction(function () use ($employees) {
            $startingNumber = 53;
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
