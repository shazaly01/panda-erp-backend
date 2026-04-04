<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Accounting\Database\Factories\VoucherDetailFactory;

class VoucherDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'account_id',
        'cost_center_id',
        'amount',
        'description'
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    // --- العلاقات ---

    // السند الأب
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    // الحساب المالي (مثل: مصروف كهرباء، إيجار)
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // مركز التكلفة الخاص بالسطر (المشروع)
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    protected static function newFactory()
    {
        return VoucherDetailFactory::new();
    }
}
