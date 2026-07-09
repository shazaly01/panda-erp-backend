<?php

declare(strict_types=1);

namespace App\Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Services\AttendanceService;
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
     * وصف الأمر
     */
    protected $description = 'تصحيح سجلات الحضور القديمة الفارغة من وقت الانصراف بناءً على نهاية وردية الموظف الفعالة';

    private AttendanceService $attendanceService;

    /**
     * حقن الخدمة الخاصة بالحضور للاستفادة من المعالجة الذكية للورديات الليلية
     */
    public function __construct(AttendanceService $attendanceService)
    {
        parent::__construct();
        $this->attendanceService = $attendanceService;
    }

    /**
     * التنفيذ الفعلي للأمر
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🚀 تم تفعيل وضع المحاكاة (Dry-Run). لن يتم تعديل أي بيانات في قاعدة البيانات.');
        } else {
            if (!$this->confirm('⚠️ تحذير: سيقوم هذا الأمر بتعديل السجلات القديمة في قاعدة البيانات. هل قمت بأخذ نسخة احتياطية (Backup) وتريد المتابعة؟')) {
                $this->error('تم إلغاء العملية.');
                return Command::FAILURE;
            }
        }

        // 1. جلب السجلات التي تفتقد لبصمة الانصراف ولديها وردية مسجلة
        $query = AttendanceLog::with(['employee', 'shift'])
            ->whereNull('check_out')
            ->whereNotNull('shift_id');

        $totalRecords = $query->count();

        if ($totalRecords === 0) {
            $this->info('✅ لا توجد أي سجلات قديمة بحاجة إلى تصحيح.');
            return Command::SUCCESS;
        }

        $this->info("🔄 تم العثور على {$totalRecords} سجل بحاجة للمعالجة. جاري البدء...");
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $processedCount = 0;
        $errorCount = 0;

        // 2. معالجة البيانات على دفعات (Chunking) لتفادي استهلاك ذاكرة السيرفر RAM
        $query->chunkById(100, function ($logs) use ($dryRun, $bar, &$processedCount, &$errorCount) {
            foreach ($logs as $log) {
                if (!$log->employee || !$log->shift) {
                    $errorCount++;
                    $bar->advance();
                    continue;
                }

                try {
                    // العزل داخل Transaction لكل سجل منفصل لضمان السلامة المطلقة
                    DB::transaction(function () use ($log, $dryRun) {

                        $employee = $log->employee;
                        $dateString = $log->date->toDateString();
                        $checkInTime = $log->check_in;
                        $targetCheckOut = $log->shift->end_time; // وقت نهاية الوردية الديناميكي (8 أو 12 ساعة)

                        if (!$dryRun) {
                            // استدعاء المعالج المركزي لإعادة حساب الساعات والدقائق والتأخير والورديات الليلية
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
                    Log::error("فشل تصحيح السجل رقم {$log->id} للموظف {$log->employee_id}: " . $e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        // 3. عرض التقرير النهائي للمشغل
        $this->table(
            ['الحالة', 'العدد'],
            [
                ['سجلات تم معالجتها بنجاح', $processedCount],
                ['سجلات فشلت المعالجة أو ناقصة البيانات', $errorCount],
                ['الإجمالي الحقيقي', $totalRecords]
            ]
        );

        $this->info('🎉 تمت العملية بنجاح.');
        return Command::SUCCESS;
    }
}
