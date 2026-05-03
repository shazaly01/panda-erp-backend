<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\HR\Models\InternetVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportInternetVouchers extends Command
{
    /**
     * اسم الأمر الذي سنكتبه في الطرفية
     */
    protected $signature = 'vouchers:import {file : المسار الكامل لملف CSV}';

    /**
     * وصف الأمر
     */
    protected $description = 'استيراد أكواد إنترنت ميكروتيك بكميات ضخمة من ملف CSV';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("❌ الملف غير موجود في المسار: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("⏳ جاري قراءة الملف واستيراد الأكواد...");

        $file = fopen($filePath, 'r');
        $batch = [];
        $count = 0;
        $batchSize = 1000; // حجم الحزمة للحفاظ على الذاكرة

        // تخطي السطر الأول إذا كان يحتوي على عناوين (Header)
        // إذا لم يكن ملفك يحتوي على عناوين، قم بحذف أو تهميش هذا السطر
        fgetcsv($file);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($file)) !== FALSE) {
                // نفترض أن الكود موجود في العمود الأول (Index 0)
                $code = trim($row[0] ?? '');

                if (!empty($code)) {
                    $batch[] = [
                        // توليد ID تسلسلي يتوافق مع DECIMAL(18,0) (استخدام Time + Random للحماية)
                        'id' => date('YmdHis') . rand(1000, 9999),
                        'code' => $code,
                        'capacity' => '1GB',
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $count++;
                }

                // إدخال الحزمة إلى قاعدة البيانات وتفريغ الذاكرة
                if (count($batch) >= $batchSize) {
                    InternetVoucher::insertOrIgnore($batch);
                    $this->line("تم إدخال {$count} كود حتى الآن...");
                    $batch = [];
                }
            }

            // إدخال أي أكواد متبقية أقل من 1000
            if (count($batch) > 0) {
                InternetVoucher::insertOrIgnore($batch);
            }

            DB::commit();
            $this->info("✅ اكتملت العملية! تم استيراد {$count} كود بنجاح.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ حدث خطأ قاتل أثناء الاستيراد: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            fclose($file);
        }
    }
}
