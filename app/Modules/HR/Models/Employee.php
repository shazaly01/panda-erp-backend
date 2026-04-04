<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Modules\HR\Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; // <--- تمت الإضافة
use App\Models\User;
use App\Modules\HR\Models\Contract; // <--- تمت الإضافة
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
        'employee_number', 'join_date', 'status', 'employment_type',
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

    // --- العلاقات ---

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

    // 🔥🔥🔥 الدوال المفقودة التي سببت المشكلة 🔥🔥🔥

    /**
     * العقد النشط الحالي للموظف
     * نستخدم latestOfMany لضمان جلب أحدث عقد في حال وجود أكثر من واحد نشط بالخطأ
     */
    public function currentContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('is_active', true)->latestOfMany();
    }

    /**
     * أرشيف جميع عقود الموظف
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    // --------------------------------------------------

    /**
     * تحديد المصنع الجديد
     */
    protected static function newFactory()
    {
        return EmployeeFactory::new();
    }
}
