<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * التصنيف الأب (في حالة التصنيفات الشجرية)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * التصنيف الأب وأجداده بشكل تكراري تصاعدي
     */
    public function parentRecursive(): BelongsTo
    {
        return $this->parent()->with('parentRecursive');
    }

    /**
     * التصنيفات الفرعية المباشرة
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * التصنيفات الفرعية وأحفادها بشكل تكراري تنازلي
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * المنتجات التابعة لهذا التصنيف
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * الحصول على المسار الهرمي الكامل للتصنيف (مثال: رئيسي > فرعي > طرفي)
     */
    public function getFullPathAttribute(): string
    {
        $segments = [$this->name];
        $current = $this;

        while ($current->parent_id !== null) {
            if ($current->relationLoaded('parent') && $current->parent !== null) {
                $current = $current->parent;
            } else {
                $current = $current->parent()->first();
            }

            if ($current === null) {
                break;
            }

            array_unshift($segments, $current->name);
        }

        return implode(' > ', $segments);
    }
}