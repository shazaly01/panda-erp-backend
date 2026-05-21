<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * تحويل مخرجات الموديل إلى مصفوفة JSON متوافقة مع لوحات عرض Panda UI
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document_type' => $this->document_type->value,
            'type_label' => $this->type_label, // المسمى العربي القادم من الـ Enum (مثل: عقد عمل)
            'url' => $this->url, // الرابط الذكي (مباشر للعام ومؤقت مشفر للمستندات الحساسة)
            'file_size' => $this->file_size, // حجم الملف (مثل: 2.45 MB)
            'extension' => $this->extension, // الامتداد (مثل: pdf)
            'mime_type' => $this->mime_type, // النوع التقني للمايم
            'documentable_id' => $this->documentable_id,
            'documentable_type' => $this->documentable_type,
            'target_info' => $this->whenLoaded('documentable'), // تحميل بيانات السجل المربوط ديناميكياً عند الطلب
            'creator_id' => $this->user_id, // معرف المستخدم الذي قام برفع وتوثيق المستند
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
