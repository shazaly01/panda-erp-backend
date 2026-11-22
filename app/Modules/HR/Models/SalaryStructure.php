<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * القواعد النشطة المرتبطة بهذا الهيكل مرتبة حسب التسلسل
     */
    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(
            SalaryRule::class,
            'structure_rules',
            'structure_id',
            'rule_id'
        )
        ->where('salary_rules.is_active', true)
        ->withPivot('sequence')
        ->orderByPivot('sequence', 'asc');
    }

    /**
     * كافة القواعد المرتبطة بهذا الهيكل (بما فيها المعطلة لأغراض العرض الإداري)
     */
    public function allRules(): BelongsToMany
    {
        return $this->belongsToMany(
            SalaryRule::class,
            'structure_rules',
            'structure_id',
            'rule_id'
        )
        ->withPivot('sequence')
        ->orderByPivot('sequence', 'asc');
    }
}