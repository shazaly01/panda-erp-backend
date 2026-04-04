<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// 1. استدعاء الفاكتوري
use App\Modules\Accounting\Database\Factories\CurrencyFactory;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate', 'is_base', 'is_active'
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeBase($query)
    {
        return $query->where('is_base', true);
    }

    // 2. إضافة دالة الربط
    protected static function newFactory()
    {
        return CurrencyFactory::new();
    }
}
