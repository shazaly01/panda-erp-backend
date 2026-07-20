<?php

declare(strict_types=1);

namespace App\Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Models\Shift;
use App\Modules\HR\Services\AttendanceService;
use App\Modules\HR\Services\ScheduleResolutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class FixPastAttendanceLogsCommand extends Command
{
    protected $signature = 'hr:fix-past-attendance {--dry-run : تشغيل محاكاة بدون الحفظ الفعلي في قاعدة البيانات}';
    protected $description = 'تصحيح سجلات الحضور القديمة الفارغة من وقت الانصراف بناءً على نهاية وردية الموظف الفعالة زمنيّاً';

    private AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        parent::__construct();
        $this->attendanceService = $attendanceService;
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🚀 تم تفعيل وضع المحاكاة (Dry-Run). لن يتم تعديل أي بيانات في قاعدة البيانات.');
        } else {
            if (!$this->confirm('⚠️ تحذير: سيقوم هذا الأمر بتعديل السجلات القديمة في قاعدة البيانات الحالية. هل قمت بأخذ نسخة احتياطية (Backup) وتريد المتابعة؟')) {
                $this->error('تم إلغاء العملية بناءً على رغبتك.');
                return Command::FAILURE;
            }
        }

        // جلب السجلات حتى مع دعم الموظفين المحذوفين حذفاً مرناً (withTrashed)
        $query = AttendanceLog::whereNull('check_out')
            ->whereHas('employee', function ($q) {
                $q->withTrashed();
            })
            ->with(['employee' => function ($q) {
                $q->withTrashed()->with(['currentContract']);
            }]);

        $totalRecords = $query->count();

        if ($totalRecords === 0) {
            $this->info('✅ لا توجد أي سجلات قديمة بحاجة إلى تصحيح في قاعدة البيانات.');
            return Command::SUCCESS;
        }

        $this->info("🔄 تم العثور على {$totalRecords} سجل بحاجة للمعالجة الديناميكية. جاري البدء...");
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        $query->chunkById(100, function ($logs) use ($dryRun, $bar, &$processedCount, &$errorCount) {
            $resolutionService = app(ScheduleResolutionService::class);
            $globalDefaultShift = Shift::where('is_active', true)->first();

            foreach ($logs as $log) {
                if (!$log->employee) {
                    $errorCount++;
                    $bar->advance();
                    continue;
                }

                try {
                    DB::transaction(function () use ($log, $dryRun, $resolutionService, $globalDefaultShift) {
                        $employee = $log->employee;
                        $dateString = $log->date->toDateString();

                        // 1. محاولة استخراج الوردية عبر محرك الجدولة التاريخي
                        $resolution = $resolutionService->resolveForDate($employee, $log->date);
                        $shift = is_array($resolution) ? ($resolution['shift'] ?? null) : ($resolution->shift ?? null);

                        // 2. Fallback: استخراج الوردية من جدول عمل الموظف (WorkingSchedule) مباشرة
                        if (!$shift && $employee->currentContract?->working_schedule_id) {
                            $scheduleId = $employee->currentContract->working_schedule_id;

                            // جلب الورديات الخاصة بجدول الموظف مباشرة من قاعدة البيانات
                            $shiftIds = DB::table('hr_working_schedule_lines')
                                ->where('working_schedule_id', $scheduleId)
                                ->pluck('shift_id');

                            $scheduleShifts = Shift::whereIn('id', $shiftIds)->get();

                            if ($scheduleShifts->isNotEmpty()) {
                                $checkInHour = Carbon::parse($log->check_in)->hour;

                                // إذا كان تسجيل الدخول مساءً (من الساعة 3 عصراً وحتى 3 فجراً)، نختار الوردية المسائية
                                if ($checkInHour >= 15 || $checkInHour < 3) {
                                    $shift = $scheduleShifts->first(function ($s) {
                                        return Carbon::parse($s->start_time)->hour >= 15;
                                    }) ?? $scheduleShifts->first();
                                } else {
                                    // الوردية الصباحية
                                    $shift = $scheduleShifts->first(function ($s) {
                                        return Carbon::parse($s->start_time)->hour < 15;
                                    }) ?? $scheduleShifts->first();
                                }
                            }
                        }

                        // 3. Fallback أخير: الوردية العامة للنظام
                        if (!$shift) {
                            $shift = $globalDefaultShift;
                        }

                        if (!$shift) {
                            throw new Exception("لم يتم العثور على أي وردية معرفة للموظف أو في النظام.");
                        }

                        $checkInTime = $log->check_in;

                        // احتساب طول الوردية بالدقائق (مع مراعاة الورديات العابرة لمنتصف الليل)
                        $shiftStart = Carbon::parse($dateString . ' ' . $shift->start_time);
                        $shiftEnd   = Carbon::parse($dateString . ' ' . $shift->end_time);

                        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                            $shiftEnd->addDay();
                        }

                        $shiftDurationMinutes = $shiftStart->diffInMinutes($shiftEnd);

                        // احتساب وقت الانصراف المستهدف
                        $targetCheckOut = Carbon::parse($checkInTime)
                            ->addMinutes($shiftDurationMinutes)
                            ->toTimeString();

                        if (!$dryRun) {
                            $this->attendanceService->processDailyAttendance(
                                $employee,
                                $dateString,
                                $checkInTime,
                                $targetCheckOut
                            );
                        }
                    });

                    $processedCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error("فشل تصحيح السجل التاريخي رقم {$log->id} للموظف صاحب المعرف {$log->employee_id}: " . $e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['حالة المعالجة الحالية', 'إجمالي عدد السجلات'],
            [
                ['سجلات تاريخية تم تصحيحها وحقنها بنجاح', $processedCount],
                ['سجلات فشلت معالجتها', $errorCount],
                ['الإجمالي الكلي للسجلات المستهدفة', $totalRecords]
            ]
        );

        $this->info('🎉 اكتملت عملية معالجة البيانات بأثر رجعي بنجاح.');
        return Command::SUCCESS;
    }
}
