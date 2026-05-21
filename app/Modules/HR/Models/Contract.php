<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\HR\Enums\SalaryFrequency;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'salary_structure_id',
        'overtime_policy_id',
        'pay_group_id',
        'working_schedule_id',
        'schedule_start_date',
        'basic_salary',
        'start_date',
        'end_date',
        'is_active',
        'attachment_path',
        'attendance_mode' // 🔥 تمت الإضافة
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'schedule_start_date' => 'date',
        'is_active' => 'boolean',
        'attendance_mode' => 'string', // 🔥 تمت الإضافة للتحقق من النوع
        'salary_frequency' => SalaryFrequency::class,
    ];

    /**
     * البوت المخصص للموديل (Model Events)
     * هنا يتم تطبيق النقطة المرجعية الثابتة لضمان مزامنة الجداول
     */
    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            // 🔥 حقن النقطة المرجعية (Epoch Date) آلياً لكل عقد جديد
            // استخدمنا بداية عام 2026 كنقطة انطلاق لجميع حسابات دورة الورديات
            if (empty($contract->schedule_start_date)) {
                $contract->schedule_start_date = '2026-01-01';
            }

            // ضمان وجود القيمة الافتراضية
            if (empty($contract->attendance_mode)) {
                $contract->attendance_mode = 'manual';
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function overtimePolicy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class, 'overtime_policy_id');
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }

    public function workingSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkingSchedule::class, 'working_schedule_id');
    }



    /**
     * الملفات والمرفقات الرسمية المؤرشفة التابعة لعقد العمل (DMS Integration)
     * العقد يمكن أن يحتوي على وثيقة العقد الموقعة، الملاحق، الشروط الإضافية
     */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Document::class, 'documentable');
    }

    /**
     * جلب ملف العقد الأساسي المعتمد
     */
    public function mainAttachment()
    {
        return $this->hasOne(\App\Models\Document::class, 'documentable_id')
            ->where('documentable_type', self::class)
            ->where('document_type', \App\Enums\DocumentType::EMPLOYEE_CONTRACT)
            ->latestOfMany();
    }
}
