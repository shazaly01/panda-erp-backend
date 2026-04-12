<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\HR\Enums\SalaryRuleCategory;
use App\Modules\HR\Enums\SalaryRuleType;

class SalaryRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'category',
        'type',
        'value',
        'percentage_of_code',
        'formula_expression',
        'account_mapping_key',
        'is_active',
        'description'
    ];

    protected $casts = [
        'category' => SalaryRuleCategory::class,
        'type' => SalaryRuleType::class,
        'value' => 'decimal:4',
        'is_active' => 'boolean',
    ];



    public function accountMapping()
    {
        // نحدد المفتاح الأجنبي (account_mapping_key) في هذا النموذج
        // والمفتاح المحلي (key) في نموذج AccountMapping
        return $this->belongsTo(
            \App\Modules\Accounting\Models\AccountMapping::class,
            'account_mapping_key',
            'key'
        );
    }
}
