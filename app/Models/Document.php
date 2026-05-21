<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'file_path',
        'disk',
        'document_type',
        'file_size',
        'mime_type',
        'extension',
        'documentable_id',
        'documentable_type',
        'user_id',
    ];

    /**
     * تحويل الحقول إلى كائنات برمجية مخصصة (Casting)
     */
    protected $casts = [
        'document_type' => DocumentType::class,
        'documentable_id' => 'integer',
    ];

    /**
     * إلحاق الرابط والاسم العربي تلقائياً عند تحويل الموديل إلى Array أو JSON
     */
    protected $appends = ['url', 'type_label'];

    /**
     * المعيار العالمي للأمان (DMS Link Generation)
     * توليد الرابط بناءً على حساسية الملف ونوع القرص المستضيف
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        // إذا كان الملف مخزناً على القرص المحمي الخاص
        if ($this->disk === 'private') {
            // توليد رابط مؤقت مشفر وصالح لمدة 15 دقيقة فقط
            return Storage::disk('private')->temporaryUrl(
                $this->file_path,
                now()->addMinutes(15)
            );
        }

        // إذا كان ملفاً عاماً (مثل صورة موظف) يتم إرجاع الرابط المباشر السريع
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * الحصول على المسمى العربي لنوع المستند للعرض في واجهات الـ Panda UI
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->document_type ? $this->document_type->label() : '';
    }

    /**
     * العلاقة متعددة الأوجه للربط مع أي موديل في نظام الـ ERP
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * تتبع المدقق: معرفة المستخدم المحاسب أو الموظف الذي قام بالأرشفة
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
