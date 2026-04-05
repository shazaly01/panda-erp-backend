<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    use SoftDeletes;

    protected $table = 'hr_loan_installments';

    protected $fillable = [
        'loan_id',
        'amount',
        'due_month',
        'status',
        'payroll_batch_id', // للإشارة إلى مسير الرواتب الذي تم الخصم من خلاله
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_month' => 'date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
