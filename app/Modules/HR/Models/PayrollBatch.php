<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;

class PayrollBatch extends Model
{
    use SoftDeletes;

    protected $table = 'payroll_batches';

    // تم تحديث الحقول لتطابق الـ Migration الاحترافي تماماً
    protected $fillable = [
        'name',               // اسم المسير (مثال: رواتب شهر 04-2026)
        'pay_period_id',      // الإضافة الجديدة
        'run_type',           // الإضافة الجديدة
        'status',             // draft, approved, paid, posted
        'approved_at',        // وقت الاعتماد
        'approved_by',        // المستخدم الذي اعتمد
        'journal_entry_id',   // رقم القيد المحاسبي
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * المستخدم الذي قام باعتماد الرواتب
     * (أبقينا اسم الدالة creator حتى لا نكسر الكود في الواجهة الأمامية،
     * ولكن ربطناها بالحقل الصحيح approved_by في قاعدة البيانات)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * القيد المحاسبي المرتبط بهذه الدفعة
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }


    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }
}
