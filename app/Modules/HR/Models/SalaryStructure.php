<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SalaryStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active'
    ];

  /**
     * القواعد المرتبطة بهذا الهيكل
     */
    public function rules(): BelongsToMany
    {
        // نحدد أسماء الأعمدة يدوياً (structure_id و rule_id)
        return $this->belongsToMany(
            SalaryRule::class,
            'structure_rules', // اسم الجدول الوسيط
            'structure_id',    // المفتاح الخاص بالهيكل (الحالي)
            'rule_id'          // المفتاح الخاص بالقاعدة (المرتبط)
        )
        ->withPivot('sequence')
        ->orderByPivot('sequence', 'asc');
    }
}
