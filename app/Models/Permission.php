<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * الحقول القابلة للتعبئة الجماعية (Mass Assignable)
     * حزمة Spatie تستخدم $guarded = ['id'] افتراضياً،
     * ولكننا نحددها هنا صراحة لزيادة الأمان ولتوضيح الهيكل الجديد.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'module',
        'group_name',
        'action_name',
        'display_name',
        'updated_at',
        'created_at',
    ];

    /**
     * دالة مساعدة (Accessor) لجلب الاسم المعروض.
     * إذا لم يكن هناك اسم معروض (صلاحية قديمة)، يتم إرجاع الاسم البرمجي.
     */
    public function getTranslatedNameAttribute(): string
    {
        return $this->display_name ?? $this->name;
    }
}
