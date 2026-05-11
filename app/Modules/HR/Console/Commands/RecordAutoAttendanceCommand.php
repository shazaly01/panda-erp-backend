<?php

declare(strict_types=1);

namespace App\Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Services\AttendanceService;
use App\Modules\HR\Services\ScheduleResolutionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class RecordAutoAttendanceCommand extends Command
{
    /**
     * اسم وتوقيع الأمر، مع إمكانية تمرير تاريخ محدد لتدارك السجلات السابقة
     * مثال: php artisan hr:record-auto-attendance --date=2026-05-10
     *
     * @var string
     */
    protected $signature = 'hr:record-auto-attendance {--date= : تاريخ التسجيل (Y-m-d). الافتراضي هو اليوم.}';

    /**
     * وصف الأمر
     *
     * @var string
     */
    protected $description = 'تسجيل الحضور والانصراف التلقائي للموظفين أصحاب العقود بنمط auto';

    private AttendanceService $attendanceService;
    private ScheduleResolutionService $scheduleResolutionService;

    /**
     * حقن الخدمات المطلوبة
     */
    public function __construct(
        AttendanceService $attendanceService,
        ScheduleResolutionService $scheduleResolutionService
    ) {
        parent::__construct();
        $this->attendanceService = $attendanceService;
        $this->scheduleResolutionService = $scheduleResolutionService;
    }

    /**
     * تنفيذ الأمر
     */
    public function handle(): int
    {
        // تحديد التاريخ (إما الممرر يدوياً أو تاريخ اليوم)
        $dateString = $this->option('date') ?? Carbon::today()->toDateString();
        $targetDate = Carbon::parse($dateString);

        $this->info("بدء تشغيل تسجيل الحضور التلقائي ليوم: {$dateString}");

        // جلب الموظفين الذين يمتلكون عقداً نشطاً وقيمة attendance_mode فيه هي auto
        $employees = Employee::whereHas('currentContract', function ($query) {
            $query->where('attendance_mode', 'auto');
        })->with('currentContract')->get();

        if ($employees->isEmpty()) {
            $this->info('لا يوجد موظفون خاضعون للتسجيل التلقائي.');
            return self::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($employees as $employee) {
            try {
                // 1. استخدام العقل المدبر لمعرفة حالة اليوم للموظف
                $resolution = $this->scheduleResolutionService->resolveForDate($employee, $targetDate);

                // 2. التسجيل فقط إذا كان اليوم يوم عمل عادي (ليس إجازة أو راحة أو طوارئ)
                if ($resolution['type'] === 'working_day' && $resolution['shift'] !== null) {
                    $shift = $resolution['shift'];

                    // التمرير كحضور وانصراف مثالي في نفس أوقات الوردية
                    $this->attendanceService->processDailyAttendance(
                        $employee,
                        $dateString,
                        $shift->start_time,
                        $shift->end_time
                    );

                    $successCount++;
                    $this->line("تم التسجيل بنجاح للموظف: {$employee->full_name}");
                } else {
                    // إذا كان يوم راحة أو إجازة، يُترك ولا يسجل له حضور لضمان دقة التقارير
                    $this->line("تم التخطي للموظف: {$employee->full_name} (الحالة: {$resolution['type']})");
                }
            } catch (Exception $e) {
                $failCount++;
                $errorMsg = "فشل التسجيل للموظف {$employee->full_name}: " . $e->getMessage();
                $this->error($errorMsg);
                Log::error($errorMsg); // تسجيل الخطأ في السجلات لمراجعته لاحقاً
            }
        }

        $this->info("اكتمل التنفيذ. نجاح: {$successCount} | فشل: {$failCount}");

        return self::SUCCESS;
    }
}
