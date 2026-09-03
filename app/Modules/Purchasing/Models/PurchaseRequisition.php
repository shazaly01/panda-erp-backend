<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\HR\Models\Department;
use App\Modules\Purchasing\Enums\RequisitionPriority;
use App\Modules\Purchasing\Enums\RequisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisition extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchasing_requisitions';

    protected $fillable = [
        'requisition_number',
        'department_id',
        'requested_by',
        'request_date',
        'required_date',
        'priority',
        'status',
        'rejection_reason',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'request_date' => 'date',
        'required_date' => 'date',
        'approved_at' => 'datetime',
        'priority' => RequisitionPriority::class,
        'status' => RequisitionStatus::class,
        'department_id' => 'integer',
        'requested_by' => 'integer',
        'approved_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
        return $this->hasMany(PurchaseRequisitionItem::class, 'requisition_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'requisition_id');
    }
}