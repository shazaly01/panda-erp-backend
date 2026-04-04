<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <-- 1. أضف هذا السطر
use Spatie\Permission\Traits\HasRoles;
use App\Modules\HR\Models\Employee;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    // 2. أضف التريت هنا
    use HasFactory, Notifiable, SoftDeletes, HasRoles, HasApiTokens;

    protected string $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'username',
        'email',
        'password',
        'default_cost_center_id',
        'default_box_id',
        'default_bank_account_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    /**
     * الملف الوظيفي المرتبط بهذا المستخدم
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }



    public function defaultCostCenter()
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\CostCenter::class, 'default_cost_center_id');
    }

    public function defaultBox()
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Box::class, 'default_box_id');
    }

    // 👈 العلاقة الجديدة للبنك
    public function defaultBankAccount()
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\BankAccount::class, 'default_bank_account_id');
    }
}
