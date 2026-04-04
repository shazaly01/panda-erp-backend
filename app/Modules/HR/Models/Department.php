<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Kalnoy\Nestedset\NodeTrait;
use App\Modules\HR\Enums\DepartmentType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\HR\Models\Employee;

class Department extends Model
{
    use HasFactory, SoftDeletes, NodeTrait;

    protected $fillable = [
        'name',
        'code',
        'type',
        'parent_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'type' => DepartmentType::class,
        'is_active' => 'boolean',
    ];

     /**
     * الموظفون في هذا القسم
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
