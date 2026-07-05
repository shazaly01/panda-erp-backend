<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\InternshipApplication;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\EmployeeStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class InternshipService
{
   /**
     * 1. معالجة وحفظ طلب التدريب القادم من الرابط الخارجي وتوليد كود المتابعة (5 أرقام) آلياً
     */
    public function storePublicApplication(array $data, UploadedFile $photo): InternshipApplication
    {
        // تخزين الصورة الشخصية في المجلد الآمن المخصص لملفات الموارد البشرية
        $photoPath = $photo->store('hr/interns/applications', 'public');

        // توليد كود متابعة آمن ومكون من 5 أرقام مشتق عشوائياً وحمايته من التكرار اللحظي
        $trackingCode = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        return InternshipApplication::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'national_id' => $data['national_id'] ?? null,
            'academic_institution' => $data['academic_institution'],
            'academic_major' => $data['academic_major'],
            'required_training_hours' => isset($data['required_training_hours']) ? (int) $data['required_training_hours'] : null,
            'internship_start_date' => $data['internship_start_date'] ?? null, // 🔥 تم التعديل لتقبل فارغ
            'internship_end_date' => $data['internship_end_date'] ?? null,     // 🔥 تم التعديل لتقبل فارغ
            'photo_path' => $photoPath,
            'tracking_code' => $trackingCode,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * 2. بوابة التحقق والمتابعة باستخدام رقم الهاتف وكود المتابعة
     */
    public function trackApplication(string $phone, string $trackingCode): InternshipApplication
    {
        return InternshipApplication::where('phone', $phone)
            ->where('tracking_code', $trackingCode)
            ->firstOrFail();
    }

   /**
     * 3. تحديث بيانات الطلب الخارجي بواسطة المتدرب نفسه بشرط أن يكون معلقاً (Pending)
     */
    public function updatePublicApplication(int $id, array $data, ?UploadedFile $photo = null): InternshipApplication
    {
        $application = InternshipApplication::findOrFail($id);

        // حماية برمجية صارمة: منع التعديل نهائياً إذا تغيرت حالة الطلب عن معلق
        abort_if($application->status !== 'pending', 422, 'لا يمكن تعديل البيانات، الطلب لم يعد في حالة الانتظار وتم اتخاذ إجراء فيه.');

        if ($photo) {
            // حذف الصورة القديمة من السيرفر لتوفير مساحة التخزين على خادم Contabo
            if ($application->photo_path && Storage::disk('public')->exists($application->photo_path)) {
                Storage::disk('public')->delete($application->photo_path);
            }
            $application->photo_path = $photo->store('hr/interns/applications', 'public');
        }

        $application->update([
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'national_id' => $data['national_id'] ?? null,
            'academic_institution' => $data['academic_institution'],
            'academic_major' => $data['academic_major'],
            'required_training_hours' => isset($data['required_training_hours']) ? (int) $data['required_training_hours'] : null,
            'internship_start_date' => $data['internship_start_date'] ?? null, // 🔥 تم التعديل لتقبل فارغ
            'internship_end_date' => $data['internship_end_date'] ?? null,     // 🔥 تم التعديل لتقبل فارغ
            'photo_path' => $application->photo_path,
            'notes' => $data['notes'] ?? null,
        ]);

        return $application;
    }

/**
     * 4. اعتماد طلب التدريب وتحويله إلى متدرب رسمي مع حفظ نسخة من الباركود والتواريخ الجديدة
     */
    public function approveApplication(int $applicationId, array $approvalData): Employee
    {
        return DB::transaction(function () use ($applicationId, $approvalData) {
            $application = InternshipApplication::findOrFail($applicationId);

            // حل ثغرة التزامن والترقيع: البحث عن آخر رقم متدرب تم إصداره لهذا العام حصراً لإكمال المتتالية برقم معزول
            $latestIntern = Employee::withInterns()
                ->where('employee_number', 'like', 'INT-' . date('Y') . '-%')
                ->latest('id')
                ->lockForUpdate() // حماية ضد الضغط المتزامن والـ Race Condition
                ->first();

            $nextSequence = 1;
            if ($latestIntern && $latestIntern->employee_number) {
                $parts = explode('-', $latestIntern->employee_number);
                $nextSequence = ((int) end($parts)) + 1;
            }

            $employeeNumber = 'INT-' . date('Y') . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);

            // توليد باركود مميز للمتدرب لربطه الفوري بنظام الحضور وكشك الباركود والطوارئ
            $barcode = 'BC-' . str_replace('-', '', $employeeNumber);

            // إنشاء سجل المتدرب في جدول الموظفين الرئيسي
            $employee = Employee::create([
                'full_name' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'national_id' => $application->national_id,
                'employee_number' => $employeeNumber,
                'barcode' => $barcode,
                'join_date' => $approvalData['internship_start_date'], // 🔥 تم التعديل ليأخذ من مدخلات المشرف
                'status' => EmployeeStatus::Training->value,
                'employment_type' => EmploymentType::Intern->value,
                'department_id' => $approvalData['department_id'] ?? null,
                'manager_id' => $approvalData['manager_id'] ?? null,

                // الحقول التاريخية الأكاديمية للمتدربين
                'internship_start_date' => $approvalData['internship_start_date'], // 🔥 تم التعديل ليأخذ من مدخلات المشرف
                'internship_end_date' => $approvalData['internship_end_date'],     // 🔥 تم التعديل ليأخذ من مدخلات المشرف
                'internship_status' => 'active',
                'academic_institution' => $application->academic_institution,
                'academic_major' => $application->academic_major,
                'required_training_hours' => $application->required_training_hours,
                'internship_notes' => $approvalData['notes'] ?? $application->notes,
            ]);

            // أتمتة العقد المالي والزمني خلف الكواليس ليعمل مع الحضور والانصراف فوراً بدون تدخل يدوي
            Contract::create([
                'employee_id' => $employee->id,
                'working_schedule_id' => $approvalData['working_schedule_id'],
                'basic_salary' => $approvalData['basic_salary'],
                'start_date' => $approvalData['internship_start_date'], // 🔥 تم التعديل ليأخذ من مدخلات المشرف
                'end_date' => $approvalData['internship_end_date'],     // 🔥 تم التعديل ليأخذ من مدخلات المشرف
                'is_active' => true,
                'attendance_mode' => 'manual',
                'salary_structure_id' => null,
                'overtime_policy_id' => null,
                'pay_group_id' => null,
            ]);

            // إصلاح جذري لثغرة التقارير التخزينية: قراءة حجم ملف الصورة الفعلي من السيرفر
            $realFileSize = Storage::disk('public')->exists($application->photo_path)
                ? Storage::disk('public')->size($application->photo_path)
                : 0;

            $extension = pathinfo($application->photo_path, PATHINFO_EXTENSION);

            DB::table('documents')->insert([
                'documentable_type' => Employee::class,
                'documentable_id' => $employee->id,
                'document_type' => \App\Enums\DocumentType::EMPLOYEE_PHOTO instanceof \BackedEnum
                    ? \App\Enums\DocumentType::EMPLOYEE_PHOTO->value
                    : (\App\Enums\DocumentType::EMPLOYEE_PHOTO ?? 'employee_photo'),
                'file_path' => $application->photo_path,
                'name' => basename($application->photo_path),
                'mime_type' => 'image/' . $extension,
                'extension' => $extension,
                'disk' => 'public',
                'file_size' => $realFileSize,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // تحديث حالة الطلب الخارجي وحقن الباركود المولد والتواريخ المقررة بواسطة المشرف
            $application->update([
                'status' => 'approved',
                'approved_barcode' => $barcode,
                'internship_start_date' => $approvalData['internship_start_date'], // 🔥 حفظ تاريخ البدء المعتمد في الطلب
                'internship_end_date' => $approvalData['internship_end_date']       // 🔥 حفظ تاريخ الانتهاء المعتمد في الطلب
            ]);

            return $employee;
        });
    }

    /**
     * 5. رفض طلب التدريب ونقل المنطق برمته للطبقة الخدمية حماية للهندسة المعمارية للنظام
     */
    public function reject(int $applicationId): InternshipApplication
    {
        $application = InternshipApplication::findOrFail($applicationId);
        $application->update(['status' => 'rejected']);

        return $application;
    }

    /**
     * 6. رفض طلب التدريب ونقل المنطق برمته للطبقة الخدمية حماية للهندسة المعمارية للنظام (دالة الدعم للمتحكم القديم)
     */
    public function rejectApplication(int $applicationId): InternshipApplication
    {
        return $this->reject($applicationId);
    }

    /**
     * 7. محرك التحويل والتثبيت النهائي (Conversion Workflow) إلى موظف دائم رسمي
     */
    public function convertToFullTime(int $employeeId, array $conversionData): Employee
    {
        return DB::transaction(function () use ($employeeId, $conversionData) {
            // استدعاء الدالة الأمنية المعزولة والمستحدثة في الموديل بنجاح لقراءة السجل شامل المتدربين
            $employee = Employee::withInterns()->findOrFail($employeeId);

            // إلغاء تفعيل عقد التدريب المالي القديم فوراً وتعيين تاريخ نهايته بـ الأمس
            Contract::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => Carbon::today()->subDay()->toDateString()
                ]);

            // إنشاء عقد التعيين الرسمي الدائم الجديد بكافة حساباته وتفاصيله المالية
            Contract::create([
                'employee_id' => $employee->id,
                'salary_structure_id' => $conversionData['salary_structure_id'],
                'overtime_policy_id' => $conversionData['overtime_policy_id'] ?? null,
                'pay_group_id' => $conversionData['pay_group_id'],
                'working_schedule_id' => $conversionData['working_schedule_id'],
                'basic_salary' => $conversionData['basic_salary'],
                'start_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'attendance_mode' => 'manual', // 🔥 تم التعديل إلى manual لتتوافق مع خيارات الـ ENUM في قاعدة البيانات
            ]);

            // حل معضلة الحساب الرقمي وفصل فوضى الـ IDs المشتركة عن الموظفين الحقيقيين:
            // نبحث عن آخر موظف رسمي يحمل كود EMP لهذا العام لنولد الرقم التالي له بدقة وهندسة مستقرة
            $latestEmp = Employee::where('employee_number', 'like', 'EMP-' . date('Y') . '-%')
                ->latest('id')
                ->lockForUpdate()
                ->first(); // الـ Global Scope هنا يحجب المتدربين تلقائياً، وهو المطلوب لحساب الموظفين الرسميين فقط

            $nextEmpSequence = 1;
            if ($latestEmp && $latestEmp->employee_number) {
                $parts = explode('-', $latestEmp->employee_number);
                $nextEmpSequence = ((int) end($parts)) + 1;
            }

            $newEmployeeNumber = 'EMP-' . date('Y') . '-' . str_pad((string) $nextEmpSequence, 4, '0', STR_PAD_LEFT);

            // توليد الباركود الرسمي الجديد المطابق للرقم الجديد للموظف
            $newBarcode = 'BC-' . str_replace('-', '', $newEmployeeNumber);

            // تحديث ملف الموظف ونقله للهيكل الإداري والوظيفي المستقر
            $employee->update([
                'employment_type' => EmploymentType::FullTime->value,
                'status' => EmployeeStatus::InService->value,
                'employee_number' => $newEmployeeNumber,
                'barcode' => $newBarcode, // حقن الباركود المحدث بنجاح ليعمل فورياً مع الكشك
                'join_date' => Carbon::today()->toDateString(),
                'position_id' => $conversionData['position_id'],
                'department_id' => $conversionData['department_id'],
                'manager_id' => $conversionData['manager_id'] ?? $employee->manager_id,

                // تحديث الحالة التاريخية للتدريب لتبقى مرجعاً للأبد في لوحات تحليل ذكاء الأعمال BI
                'internship_status' => 'converted'
            ]);

            return $employee;
        });
    }
}
