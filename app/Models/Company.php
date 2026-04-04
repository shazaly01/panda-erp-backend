<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // 1. استيراد الحذف الناعم

class Company extends Model
{
    use HasFactory, SoftDeletes; // 2. استخدام الحذف الناعم

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // 3. تحديد الحقول القابلة للتعبئة
    protected $fillable = [
        'name',
        'commercial_record',
        'tax_number',
        'license_number',
        'address',
        'owner_name',
    ];

    /**
     * Get the projects for the company.
     */
    // 4. تعريف علاقة "لديه العديد من" المشاريع
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

  // أضف هذه العلاقة في كلا الملفين
public function documents()
{
    return $this->morphMany(Document::class, 'documentable');
}


protected static function boot()
    {
        parent::boot();

        static::deleting(function ($company) {
            // 1. فحص وجود مشاريع
            if ($company->projects()->exists()) {
                // نستخدم abort لإرجاع خطأ 409 (Conflict) مع رسالة واضحة
                abort(409, 'لا يمكن حذف الشركة لوجود مشاريع مرتبطة بها. يرجى حذف المشاريع أولاً.');
            }

            // 2. فحص وجود مستندات (اختياري، إذا كنت تعتبر المستندات أبناء يجب حذفهم)
            if ($company->documents()->exists()) {
                abort(409, 'لا يمكن حذف الشركة لوجود مستندات مرفقة بها.');
            }
        });
    }
}
