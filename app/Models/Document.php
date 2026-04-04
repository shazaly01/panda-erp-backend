<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage; // تأكد من استيراد الـ Storage

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'file_path',
        'documentable_id',
        'documentable_type',
    ];

    // --- 1. الإضافة هنا: نخبر لارافيل بإرجاع حقل url دائماً مع البيانات ---
    protected $appends = ['url'];

    // --- 2. الإضافة هنا: دالة بناء الرابط (Accessor) ---
    /**
     * Get the full URL for the document file.
     */
    public function getUrlAttribute(): ?string
    {
        // نتحقق من وجود مسار
        if ($this->file_path) {
            // نستخدم disk('public') لضمان الإشارة للمكان الصحيح
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }


public function documentable()
{
    return $this->morphTo();
}
}
