<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    /**
     * معالجة وحفظ المستند بطريقة احترافية داخل الـ Transaction لضمان سلامة البيانات
     */
    public function uploadDocument(
        UploadedFile $file,
        string $name,
        DocumentType $documentType,
        string $targetType,
        int $targetId,
        int $userId
    ): Document {
        return DB::transaction(function () use ($file, $name, $documentType, $targetType, $targetId, $userId) {

            // 1. تحديد قرص التخزين تلقائياً بناءً على حساسية نوع المستند المحددة في الـ Enum
            $disk = $documentType->disk();

            // 2. استخراج الخصائص التقنية للملف (Metadata) لفرزها وعرضها بـ Panda UI
            $fileSize = $this->formatBytes($file->getSize());
            $mimeType = $file->getClientMimeType();
            $extension = $file->getClientOriginalExtension();

            // 3. تخزين الملف الفعلي في المجلد المعزول الخاص به بناءً على التصنيف لسهولة الفهرسة وسرعة التصفح
            $folder = "dms/{$documentType->value}";
            $path = $file->store($folder, $disk);

            // 4. ترجمة الاسم النصي القادم من الواجهة إلى الموديل البرمجي المقابل في الـ ERP
            $modelType = match($targetType) {
                'company' => \App\Models\Company::class,
                'project' => \App\Models\Project::class,
                'employee' => \App\Modules\HR\Models\Employee::class,
                'contract' => \App\Modules\HR\Models\Contract::class,
            };

            // 5. تسجيل المستند في قاعدة البيانات ككتلة برمجية مكتملة ومتعقبة تحت هوية المستخدم المنفذ
            return Document::create([
                'name' => $name,
                'file_path' => $path,
                'disk' => $disk,
                'document_type' => $documentType,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'documentable_id' => $targetId,
                'documentable_type' => $modelType,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * حذف سجل المستند برمجياً (Soft Delete) لتجنب الفقدان العشوائي لملفات المنشأة المالية والقانونية
     */
    public function deleteDocument(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $document->delete();
        });
    }

    /**
     * تحويل حجم الملف الرقمي إلى صيغة مقروءة وواضحة للمستخدم في لوحة التحكم
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $fileBytes = max($bytes, 0);
        $pow = floor(($fileBytes ? log($fileBytes) : 0) / log(1024));
        $pow = (int) min($pow, count($units) - 1);
        $fileBytes /= pow(1024, $pow);

        return round($fileBytes, $precision) . ' ' . $units[$pow];
    }
}
