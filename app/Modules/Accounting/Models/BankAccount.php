<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// 1. استدعاء الفاكتوري
use App\Modules\Accounting\Database\Factories\BankAccountFactory;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_name', 'account_name', 'account_number', 'iban',
        'account_id', 'currency_id', 'branch_id', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // 2. إضافة دالة الربط
    protected static function newFactory()
    {
        return BankAccountFactory::new();
    }
}
