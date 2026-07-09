<?php

declare(strict_types=1);

namespace App\Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Services\AttendanceService;
use App\Modules\HR\Services\ScheduleResolutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class FixPastAttendanceLogsCommand extends Command
{
    /**
     * اسم الأمر الذي سيتم تشغيله عبر السيرفر مع خيار المحاكاة التجريبية (--dry-run)
     */
    protected $signature = 'hr:fix-past-attendance {--dry-run : تشغيل محاكاة بدون الحفظ الفعلي في قاعدة البيانات}';

    /**
     * وصف الأمر في قائمة أوامر الـ Artisan
     */
    protected $description = 'تصحيح سجلات الحضور القديمة الفارغة من وقت الانصراف بناءً على نهاية وردية الموظف الفعالة زمنيّاً';

    private AttendanceService $attendanceService;

    /**
     * حقن خدمة الحضور المركزية
     */
    public function __construct(AttendanceService $attendanceService)
    {
        parent::__construct();
        $this->attendanceService = $attendanceService;
    }

    /**
     * التنفيذ الفعلي للأمر البرمي
     */
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

        // الاستعلام عن كافة السجلات التاريخية التي تفتقد لبصمة الانصراف
        $query = AttendanceLog::with(['employee'])->whereNull('check_out');
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

        // معالجة البيانات على دفعات (Chunking) لمنع استهلاك ذاكرة السيرفر RAM وعزل المعالجة
        $query->chunkById(100, function ($logs) use ($dryRun, $bar, &$processedCount, &$errorCount) {
            // استدعاء العقل المدبر للجدولة برمجياً لفك تشفير الجداول التاريخية لكل موظف
            $resolutionService = app(ScheduleResolutionService::class);

            foreach ($logs as $log) {
                if (!$log->employee) {
                    $errorCount++;
                    $bar->advance();
                    continue;
                }

                try {
                    // فتح Transaction لكل سجل على حدة لضمان السلامة المطلقة للبيانات عند حدوث أي استثناء تزامني
                    DB::transaction(function () use ($log, $dryRun, $resolutionService) {
                        $employee = $log->employee;
                        $dateString = $log->date->toDateString();

                        // استخراج الوردية الفعالة للموظف في ذلك اليوم التاريخي (عبر العقود والجداول التتابعية)
                        $resolution = $resolutionService->resolveForDate($employee, $log->date);
                        $shift = $resolution['shift'] ?? null;

                        if (!$shift) {
                            throw new Exception("الموظف لا يمتلك أي وردية نشطة مسندة لجدول عمله في هذا التاريخ.");
                        }

                        $checkInTime = $log->check_in;
                        $targetCheckOut = $shift->end_time; // أخذ وقت نهاية الوردية الفعلي (سواء 8 أو 12 ساعة)

                        if (!$dryRun) {
                            // حقن البيانات في المعالج المركزي لإعادة احتساب الساعات والدقائق والتداخلات الليلية بشكل صحيح
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

        // طباعة جدول تفصيلي بالنتيجة النهائية للمشغل
        $this->table(
            ['حالة المعالجة الحالية', 'إجمالي عدد السجلات'],
            [
                ['سجلات تاريخية تم تصحيحها وحقنها بنجاح', $processedCount],
                ['سجلات فشلت معالجتها (لعدم وجود جدول أو عقد بالخلفية)', $errorCount],
                ['الإجمالي الكلي للسجلات المستهدفة', $totalRecords]
            ]
        );

        $this->info('🎉 اكتملت عملية معالجة البيانات بأثر رجعي بنجاح.');
        return Command::SUCCESS;
    }
}
