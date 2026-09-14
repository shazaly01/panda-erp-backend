<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Database\Factories\VoucherDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VoucherDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'account_id',
        'cost_center_id',
        'amount',
        'description',
        'party_type',
        'party_id',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'reference_id' => 'integer',
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

    /**
     * العلاقة البوليمورفية لربط السطر بالمستند الأصلي (فاتورة مشتريات / فاتورة مبيعات)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    protected static function newFactory(): VoucherDetailFactory
    {
        return VoucherDetailFactory::new();
    }
}