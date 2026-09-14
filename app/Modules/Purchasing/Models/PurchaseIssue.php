<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchasing\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseIssue extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_issues';

    protected $fillable = [
        'issue_number',
        'requisition_id',
        'warehouse_id',
        'department_id',
        'recipient_id',
        'issue_date',
        'status',
        'total_cost',
        'issued_by',
        'issued_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'issued_at' => 'datetime',
        'status' => IssueStatus::class,
        'requisition_id' => 'integer',
        'warehouse_id' => 'integer',
        'department_id' => 'integer',
        'recipient_id' => 'integer',
        'issued_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'total_cost' => 'decimal:4',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseIssueItem::class, 'issue_id');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}