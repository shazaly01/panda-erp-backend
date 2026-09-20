<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_settings';

    protected $fillable = [
        'base_currency_id',
        'active_modules',
        'company_name',
    ];

    protected $casts = [
        'active_modules' => 'array',
    ];

    /**
     * علاقة العملة الأساسية للنظام
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }
}