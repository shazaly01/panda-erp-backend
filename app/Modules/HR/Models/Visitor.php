<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visitor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * اسم الجدول المرتبط بالموديل.
     *
     * @var string
     */
    protected $table = 'hr_visitors';

    /**
     * الحقول القابلة للتعبئة الجماعية (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'national_id',
        'company_from',
        'purpose',
        'employee_id',
        'status',
        'qr_token',
        'visit_date',
        'checked_in_at',
        'checked_out_at',
        'gatekeeper_id',
    ];

    /**
     * تحويل أنواع البيانات تلقائياً (Casting).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * علاقة الزائر بالموظف المُستضيف (Host Employee).
     * الزائر يتبع لموظف واحد في المؤسسة.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * علاقة الزائر بموظف الأمن أو الاستقبال (Gatekeeper).
     * الموظف المسؤول عن تسجيل حركة الدخول والخروج من جدول المستخدمين الأساسي.
     */
    public function gatekeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gatekeeper_id');
    }
}
