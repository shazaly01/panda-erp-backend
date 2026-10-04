<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollBatch extends Model
{
    use SoftDeletes;

    protected $table = 'payroll_batches';

    protected $fillable = [
        'number',
        'name',
        'pay_period_id',
        'run_type',
        'status',
        'approved_at',
        'approved_by',
        'journal_entry_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * المستخدم الذي قام باعتماد وترحيل المسير
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * القيد المحاسبي المولد آلياً في شجرة الحسابات
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * الفترة المالية التابع لها المسير
     */
    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }

    /**
     * قسائم الرواتب الفردية للموظفين التابعة لهذه الدفعة
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'payroll_batch_id');
    }
}