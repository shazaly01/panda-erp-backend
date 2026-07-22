<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Modules\HR\Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\Department;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Models\EmployeeShift;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\MaritalStatus;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name', 'date_of_birth', 'gender', 'marital_status',
        'national_id', 'email', 'phone', 'address',
        'employee_number', 'barcode', 'join_date', 'status', 'employment_type',
        'department_id', 'position_id', 'manager_id', 'user_id',
        'internship_start_date', 'internship_end_date', 'internship_status',
        'academic_institution', 'academic_major', 'required_training_hours', 'internship_notes'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'join_date' => 'date',
        'status' => EmployeeStatus::class,
        'employment_type' => EmploymentType::class,
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
        'internship_start_date' => 'date',
        'internship_end_date' => 'date',
    ];

    /**
     * تفعيل الحماية المعمارية عبر الـ Global Scope
     * لعزل المتدربين تلقائياً عن كافة شاشات ومحركات النظام القديم (كالرواتب والإجازات)
     */
    protected static function booted(): void
    {
        static::addGlobalScope('exclude_interns', function (Builder $query) {
            $query->where('employment_type', '!=', EmploymentType::Intern->value);
        });
    }

    // --- آليات كسر العزل الآمنة المستدعاة في الـ الخدمة والمتحكم الجديد ---

    /**
     * جلب الموظفين مع المتدربين (لتعويض الدالة المكسورة في الخدمة)
     */
    public static function withInterns(): Builder
    {
        return static::withoutGlobalScope('exclude_interns');
    }

    /**
     * جلب المتدربين فقط وعزل الموظفين الرسميين (معدلة لتلائم الـ Global Scope)
     */
   public function scopeOnlyInterns(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exclude_interns')
            ->where('employment_type', EmploymentType::Intern->value)
            ->where('internship_status', 'active');
    }
    /**
     * جلب المتدربين المنتهية فترتهم التدريبية ولم يتم تثبيتهم بعد
     * (تاريخ انتهاء التدريب أصغر من تاريخ اليوم وحالة التدريب ما زالت نشطة)
     */
    public function scopeOnlyCompletedInterns(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exclude_interns')
            ->where('employment_type', EmploymentType::Intern->value)
            ->where('internship_status', 'completed');
    }


public function activeContract(): HasOne
{
    return $this->hasOne(Contract::class)
        ->where('is_active', true)
        ->where('start_date', '<=', now()->toDateString())
        ->where(function ($query) {
            $query->whereNull('end_date')
                  ->orWhere('end_date', '>=', now()->toDateString());
        })
        ->latestOfMany('start_date');
}
    // --- العلاقات الأساسية للمنظومة ---

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('is_active', true)->latestOfMany();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(EmployeeBankAccount::class)->where('is_primary', true);
    }

    public function supervisedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'department_supervisors',
            'employee_id',
            'department_id'
        )->withTimestamps();
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'employee_id');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Document::class, 'documentable');
    }

    public function profilePhoto(): HasOne
    {
        return $this->hasOne(\App\Models\Document::class, 'documentable_id')
            ->where('documentable_type', self::class)
            ->where('document_type', \App\Enums\DocumentType::EMPLOYEE_PHOTO)
            ->latestOfMany();
    }

    // --- العلاقات المستحدثة لحل انهيار الـ EmployeeResource ---

    /**
     * سجل ورديات الموظف/المتدرب التاريخية
     */
    public function employeeShifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'employee_id');
    }

    /**
     * الوردية الأخيرة النشطة
     */
    public function latestShift(): HasOne
    {
        return $this->hasOne(EmployeeShift::class, 'employee_id')->latestOfMany();
    }

    // --- العلاقات والدوال الخاصة بنظام أذونات الأمن والسلامة (HSE) ---

    /**
     * جميع أذونات الخروج المؤقت الخاصة بالموظف
     */
    public function leavePasses(): HasMany
    {
        return $this->hasMany(HrLeavePass::class, 'employee_id');
    }

    /**
     * محرك الحالات اللحظي لفحص وتحديد التواجد الفعلي داخل أو خارج أسوار المنشأة
     */
    public function getCurrentPresenceStatusAttribute(): string
    {
        $today = now()->toDateString();

        // 1. فحص ما إذا كان الموظف قد سجل بصمة حضور اليوم
        $hasAttendedToday = $this->attendanceLogs()
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->exists();

        if (!$hasAttendedToday) {
            return 'Outside_Duty';
        }

        // 2. فحص ما إذا كان خارج أسوار المؤسسة بموجب إذن خروج فعال
        $isCurrentlyOutOnPass = $this->leavePasses()
            ->where('date', $today)
            ->where('status', 'out')
            ->whereNotNull('actual_leave_at')
            ->whereNull('actual_return_at')
            ->exists();

        if ($isCurrentlyOutOnPass) {
            return 'Temporary_Out';
        }

        return 'Inside';
    }

    protected static function newFactory()
    {
        return EmployeeFactory::new();
    }
}
