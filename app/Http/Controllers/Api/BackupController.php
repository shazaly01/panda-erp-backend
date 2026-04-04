<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * عرض قائمة النسخ الاحتياطية الموجودة.
     */
    public function index()
    {
        // اسم المجلد الذي حددناه في config/backup.php (عادة هو اسم التطبيق)
        // تأكد أن هذا يطابق الاسم في 'name' داخل config/backup.php
        // إذا لم تكن متأكداً، افتح storage/app وانظر اسم المجلد
        $backupName = config('backup.backup.name');

        // التحقق من وجود المجلد
        if (!Storage::disk('local')->exists($backupName)) {
            return response()->json(['data' => []]);
        }

        $files = Storage::disk('local')->files($backupName);
        $backups = [];

        foreach ($files as $file) {
            // نأخذ فقط ملفات zip
            if (substr($file, -4) == '.zip') {
                $backups[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $this->formatSize(Storage::disk('local')->size($file)),
                    'date' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($file)),
                    // رابط التحميل (سننشئ هذا المسار لاحقاً)
                    // ملاحظة: التحميل سيتم عبر دالة download وليس رابط مباشر للأمان
                ];
            }
        }

        // ترتيب التنازلي (الأحدث أولاً)
        $backups = array_reverse($backups);

        return response()->json(['data' => $backups]);
    }

    /**
     * إنشاء نسخة احتياطية جديدة.
     */
    public function store()
    {
        try {
            // تشغيل الأمر في الخلفية
            // ملاحظة: --only-db لنسخ القاعدة فقط (أسرع) إذا أردت،
            // لكن هنا سنشغل الباكب الكامل كما ضبطناه
            Artisan::call('backup:run');

            // نأخذ مخرجات الأمر للتأكد (اختياري)
            $output = Artisan::output();

            return response()->json([
                'message' => 'تم إنشاء النسخة الاحتياطية بنجاح.',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل إنشاء النسخة الاحتياطية.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تنزيل ملف النسخة الاحتياطية.
     */
    public function download(Request $request)
    {
        $fileName = $request->query('file_name');
        $backupName = config('backup.backup.name');
        $path = $backupName . '/' . $fileName;

        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'الملف غير موجود.'], 404);
        }

        // استخدام دالة download الخاصة بـ Storage لإرسال الملف للمستخدم
        return Storage::disk('local')->download($path);
    }

    /**
     * حذف نسخة احتياطية.
     */
    public function destroy(Request $request)
    {
        $fileName = $request->query('file_name');
        $backupName = config('backup.backup.name');
        $path = $backupName . '/' . $fileName;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
            return response()->json(['message' => 'تم حذف النسخة بنجاح.']);
        }

        return response()->json(['message' => 'الملف غير موجود.'], 404);
    }

    /**
     * دالة مساعدة لتنسيق الحجم.
     */
    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
