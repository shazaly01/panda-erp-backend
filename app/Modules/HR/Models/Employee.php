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
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\MaritalStatus;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name', 'date_of_birth', 'gender', 'marital_status',
        'national_id', 'email', 'phone', 'address',
        'employee_number', 'barcode', 'join_date', 'status', 'employment_type',
        'department_id', 'position_id', 'manager_id', 'user_id'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'join_date' => 'date',
        'status' => EmployeeStatus::class,
        'employment_type' => EmploymentType::class,
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
    ];

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

    // --- العلاقات والدوال المستحدثة لنظام أذونات الأمن والسلامة (HSE) ---

    /**
     * جميع أذونات الخروج المؤقت الخاصة بالموظف
     */
    public function leavePasses(): HasMany
    {
        return $this->hasMany(HrLeavePass::class, 'employee_id');
    }

    /**
     * محرك الحالات اللحظي لفحص وتحديد التواجد الفعلي للموظف الآن داخل أو خارج أسوار المنشأة
     * يخدم شاشة حصر الطوارئ (Muster Evacuation List) لمنع التخمين وحماية الأرواح
     * @return string (Inside | Temporary_Out | Outside_Duty)
     */
    public function getCurrentPresenceStatusAttribute(): string
    {
        $today = now()->toDateString();

        // 1. فحص ما إذا كان الموظف قد سجل بصمة حضور اليوم داخل المؤسسة أصلاً
        $hasAttendedToday = $this->attendanceLogs()
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->exists();

        if (!$hasAttendedToday) {
            return 'Outside_Duty'; // خارج الدوام الرسمي اليوم أو انصرف نهائياً
        }

        // 2. فحص ما إذا كان خارج أسوار المؤسسة حالياً بموجب إذن خروج فعال أثبته الأمن عند البوابة
        $isCurrentlyOutOnPass = $this->leavePasses()
            ->where('date', $today)
            ->where('status', 'out')
            ->whereNotNull('actual_leave_at')
            ->whereNull('actual_return_at')
            ->exists();

        if ($isCurrentlyOutOnPass) {
            return 'Temporary_Out'; // خارج المنشأة مؤقتاً بأمان (بموجب إذن رسمي)
        }

        return 'Inside'; // متواجد حالياً داخل المبنى كلياً (يجب إخلاؤه فوراً في حالات الطوارئ)
    }

    protected static function newFactory()
    {
        return EmployeeFactory::new();
    }
}
