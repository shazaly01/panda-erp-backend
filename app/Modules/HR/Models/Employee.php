<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Models\User;
use App\Modules\HR\Database\Factories\EmployeeFactory;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\MaritalStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name', 'date_of_birth', 'gender', 'marital_status',
        'national_id', 'email', 'phone', 'address',
        'employee_number', 'barcode', 'tracking_code', 'join_date', 'status', 'employment_type',
        'application_type',
        'department_id', 'position_id', 'manager_id', 'user_id',
        'internship_start_date', 'internship_end_date', 'internship_status',
        'academic_institution', 'academic_major', 'required_training_hours', 'internship_notes',
    ];

    protected $casts = [
        'date_of_birth'         => 'date',
        'join_date'             => 'date',
        'status'                => EmployeeStatus::class,
        'employment_type'       => EmploymentType::class,
        'gender'                => Gender::class,
        'marital_status'        => MaritalStatus::class,
        'internship_start_date' => 'date',
        'internship_end_date'   => 'date',
    ];

    /**
     * تفعيل الحماية المعمارية عبر الـ Global Scope
     * لعزل المتدربين تلقائياً عن كافة شاشات ومحركات النظام القديم (كالرواتب والإجازات)
     */
    protected static function booted(): void
    {
        static::addGlobalScope('exclude_interns', function (Builder $query): void {
            $query->where('employment_type', '!=', EmploymentType::Intern->value);
        });
    }

    // --- آليات كسر العزل الآمنة المستدعاة في الخدمة والمتحكم ---

    public static function withInterns(): Builder
    {
        return static::withoutGlobalScope('exclude_interns');
    }

    public function scopeOnlyInterns(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exclude_interns')
            ->where('employment_type', EmploymentType::Intern->value)
            ->where('internship_status', 'active');
    }

    public function scopeOnlyCompletedInterns(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exclude_interns')
            ->where('employment_type', EmploymentType::Intern->value)
            ->where('internship_status', 'completed');
    }

    // --- دوال جلب العقود السارية حسب الفترة أو التاريخ ---

    /**
     * جلب العقد الساري والمتوافق مع الفترة المالية المحددة (يدعم التحميل المسبق لمنع N+1)
     */
    public function contractForPeriod(PayPeriod $period): ?Contract
    {
        $startDate = $period->start_date->toDateString();
        $endDate = $period->end_date->toDateString();

        if ($this->relationLoaded('contracts')) {
            return $this->contracts
                ->filter(function (Contract $contract) use ($period, $startDate, $endDate): bool {
                    if (!$contract->is_active || !$contract->salary_structure_id) {
                        return false;
                    }

                    if ($period->pay_group_id && (int) $contract->pay_group_id !== (int) $period->pay_group_id) {
                        return false;
                    }

                    $contractStart = $contract->start_date?->toDateString();
                    $contractEnd = $contract->end_date?->toDateString();

                    if (!$contractStart || $contractStart > $endDate) {
                        return false;
                    }

                    if ($contractEnd !== null && $contractEnd < $startDate) {
                        return false;
                    }

                    return true;
                })
                ->sortByDesc(fn (Contract $c) => $c->start_date?->toDateString())
                ->first();
        }

        return $this->contracts()
            ->where('is_active', true)
            ->when($period->pay_group_id, fn ($q) => $q->where('pay_group_id', $period->pay_group_id))
            ->whereNotNull('salary_structure_id')
            ->where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate): void {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $startDate);
            })
            ->latest('start_date')
            ->first();
    }

    /**
     * جلب العقد الساري في تاريخ محدد (يدعم التحميل المسبق لمنع N+1)
     */
    public function contractForDate(Carbon|string $date): ?Contract
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        if ($this->relationLoaded('contracts')) {
            return $this->contracts
                ->filter(function (Contract $contract) use ($dateString): bool {
                    if (!$contract->is_active) {
                        return false;
                    }

                    $contractStart = $contract->start_date?->toDateString();
                    $contractEnd = $contract->end_date?->toDateString();

                    if (!$contractStart || $contractStart > $dateString) {
                        return false;
                    }

                    if ($contractEnd !== null && $contractEnd < $dateString) {
                        return false;
                    }

                    return true;
                })
                ->sortByDesc(fn (Contract $c) => $c->start_date?->toDateString())
                ->first();
        }

        return $this->contracts()
            ->where('is_active', true)
            ->where('start_date', '<=', $dateString)
            ->where(function ($query) use ($dateString): void {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $dateString);
            })
            ->latest('start_date')
            ->first();
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)
            ->where('is_active', true)
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($query): void {
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

    public function documents(): MorphMany
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

    // --- العلاقات والدوال الخاصة بنظام أذونات الأمن والسلامة (HSE) ---

    public function leavePasses(): HasMany
    {
        return $this->hasMany(HrLeavePass::class, 'employee_id');
    }

    public function getCurrentPresenceStatusAttribute(): string
    {
        $today = now()->toDateString();

        $hasAttendedToday = $this->attendanceLogs()
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->exists();

        if (!$hasAttendedToday) {
            return 'Outside_Duty';
        }

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