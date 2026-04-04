<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Kalnoy\Nestedset\NodeTrait;
use App\Modules\Accounting\Enums\CostCenterType;
use App\Modules\Accounting\Database\Factories\CostCenterFactory;

class CostCenter extends Model
{
    use HasFactory, SoftDeletes, NodeTrait;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'is_active',
        'notes',
        'is_branch',
        'code_prefix',
    ];

    protected $casts = [
        'type' => CostCenterType::class, // ربط بالـ Enum
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($costCenter) {
            // 1. حماية الشجرة: ممنوع حذف مركز رئيسي تحته مراكز فرعية
            if ($costCenter->children()->exists()) {
                abort(409, 'لا يمكن حذف مركز تكلفة رئيسي يحتوي على مراكز فرعية.');
            }

            // 2. حماية القيود: (سنفعلها لاحقاً عند ربط القيود)
            /*
            if ($costCenter->journalDetails()->exists()) {
                abort(409, 'لا يمكن حذف مركز التكلفة لوجود حركات مالية عليه.');
            }
            */
        });
    }

    protected static function newFactory()
{
    return CostCenterFactory::new();
}
}
