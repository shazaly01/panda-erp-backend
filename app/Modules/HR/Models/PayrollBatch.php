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
        'disbursed_amount',
        'disbursement_percentage',
        'approved_at',
        'approved_by',
        'journal_entry_id',
    ];

    protected $casts = [
        'disbursed_amount'        => 'decimal:2',
        'disbursement_percentage' => 'decimal:2',
        'approved_at'             => 'datetime',
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

    /**
     * التحقق من قابلية المسير للتعديل زمنياً:
     * يُسمح بالتعديل طالما المسير معتمد ولا يوجد مسير معتمد لفترة أحدث منه لنفس مجموعة الدفع ونوع المسير
     */
    public function isEditable(): bool
    {
        if ($this->status !== 'posted') {
            return false;
        }

        $period = $this->payPeriod;
        if (!$period) {
            return false;
        }

        $hasNewerPostedBatch = self::query()
            ->where('id', '!=', $this->id)
            ->where('status', 'posted')
            ->where('run_type', $this->run_type)
            ->whereHas('payPeriod', function ($query) use ($period): void {
                $query->where('pay_group_id', $period->pay_group_id)
                      ->where('start_date', '>', $period->start_date->format('Y-m-d'));
            })
            ->exists();

        return !$hasNewerPostedBatch;
    }
}