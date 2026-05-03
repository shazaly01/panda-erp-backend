<?php

declare(strict_types=1);

namespace App\Modules\HR\Services;

use App\Modules\HR\Models\InternetVoucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Exception;

class InternetVoucherService
{
    /**
     * استيراد الأكواد من ملف CSV بنظام الحزم
     */
    public function importFromCsv(UploadedFile $file, bool $hasHeader = true, string $capacity = '1GB'): array
    {
        $filePath = $file->getRealPath();
        $fileHandle = fopen($filePath, 'r');

        if (!$fileHandle) {
            throw new Exception('لا يمكن قراءة الملف المرفوع.');
        }

        $batch = [];
        $actualInsertedCount = 0;
        $batchSize = 1000;

        if ($hasHeader) {
            fgetcsv($fileHandle);
        }

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($fileHandle)) !== FALSE) {
                $code = trim($row[0] ?? '');

                if (!empty($code)) {
                    $batch[] = [
                        'id' => date('YmdHis') . rand(1000, 9999),
                        'code' => $code,
                        'capacity' => $capacity,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (count($batch) >= $batchSize) {
                    $actualInsertedCount += InternetVoucher::insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (count($batch) > 0) {
                $actualInsertedCount += InternetVoucher::insertOrIgnore($batch);
            }

            DB::commit();
            return ['imported_count' => $actualInsertedCount];

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("حدث خطأ أثناء الاستيراد: " . $e->getMessage());
        } finally {
            fclose($fileHandle);
        }
    }

    /**
     * الصرف اليدوي لكود الإنترنت لموظف (مع حماية التكرار)
     */
    public function assignVoucherManually(int|string $employeeId): InternetVoucher
    {
        return DB::transaction(function () use ($employeeId) {

            // 1. التحقق من التكرار: هل أخذ الموظف كوداً اليوم؟ (تنفيذ شرطك بحذافيره)
            $today = now()->toDateString();

            $existingVoucher = InternetVoucher::where('employee_id', $employeeId)
                ->whereDate('assigned_at', $today)
                ->first();

            // إذا كان لديه كود اليوم، نُرجع له نفس الكود ولا نسحب كوداً جديداً
            if ($existingVoucher) {
                return $existingVoucher;
            }

            // 2. إذا لم يكن لديه كود، نبحث عن كود متاح مع قفله (Pessimistic Locking) لمنع التضارب
            $voucher = InternetVoucher::where('status', 'available')
                ->lockForUpdate() // 🌟 مهم جداً لمنع موظفين من أخذ نفس الكود في نفس اللحظة
                ->first();

            if (!$voucher) {
                throw new Exception('لا توجد أكواد إنترنت متاحة في المخزون حالياً. يرجى رفع ملف جديد.');
            }

            // 3. تحديث بيانات الكود وصرفه
            $voucher->update([
                'status' => 'assigned',
                'employee_id' => $employeeId,
                'assigned_at' => now(),
            ]);

            return $voucher;
        });
    }



    /**
     * الصرف الآلي عند تسجيل الحضور
     */
    public function assignAutoVoucher(int|string $employeeId, int|string $attendanceLogId): ?InternetVoucher
    {
        return DB::transaction(function () use ($employeeId, $attendanceLogId) {

            $today = now()->toDateString();

            // 1. التحقق من التكرار: هل أخذ كوداً اليوم؟ (Idempotency)
            $existingVoucher = InternetVoucher::where('employee_id', $employeeId)
                ->whereDate('assigned_at', $today)
                ->first();

            if ($existingVoucher) {
                return $existingVoucher; // إرجاع نفس الكود القديم
            }

            // 2. سحب كود جديد مع القفل لمنع التضارب
            $voucher = InternetVoucher::where('status', 'available')
                ->lockForUpdate()
                ->first();

            // إذا انتهت الأكواد، نرمي استثناء (سيتم التقاطه بصمت في خدمة الحضور)
            if (!$voucher) {
                throw new Exception("مخزون الأكواد فارغ للموظف ID: {$employeeId}");
            }

            // 3. التحديث والربط بسجل الحضور
            $voucher->update([
                'status' => 'assigned',
                'employee_id' => $employeeId,
                'attendance_log_id' => $attendanceLogId, // 🌟 التوثيق الأمني
                'assigned_at' => now(),
            ]);

            return $voucher;
        });
    }
}
