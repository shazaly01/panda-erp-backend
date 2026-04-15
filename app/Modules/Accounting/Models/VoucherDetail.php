<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        'description',
        'party_type', // <-- تمت الإضافة: نوع الطرف (موظف، مورد، عميل)
        'party_id'    // <-- تمت الإضافة: رقم الطرف
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    // --- العلاقات ---

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * العلاقة السحرية (Polymorphic) لجلب بيانات الطرف بغض النظر عن نوعه
     */
    public function party(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'party_type', 'party_id');
    }

    protected static function newFactory()
    {
        return VoucherDetailFactory::new();
    }
}
